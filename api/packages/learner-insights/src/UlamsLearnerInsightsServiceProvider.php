<?php

namespace Ulams\LearnerInsights;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Ulams\Ai\Fake\FakeResponders;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\Auth\Events\AccountDeleted;
use Ulams\Auth\Events\Login;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Events\CourseStarted;
use Ulams\Courses\Events\TopicFinished;
use Ulams\Courses\Events\TopicProgressUpdated;
use Ulams\Courses\Models\H5PUserProgress;
use Ulams\LearnerInsights\Console\BackfillCommand;
use Ulams\LearnerInsights\Console\CohortStatsCommand;
use Ulams\LearnerInsights\Console\EvalCommand;
use Ulams\LearnerInsights\Console\EvaluateCommand;
use Ulams\LearnerInsights\Console\PruneCommand;
use Ulams\LearnerInsights\Console\RetireRemediationsCommand;
use Ulams\LearnerInsights\Console\TimelineCommand;
use Ulams\LearnerInsights\Events\LearnerAtRisk;
use Ulams\LearnerInsights\Events\LearnerRecovered;
use Ulams\LearnerInsights\Events\LearnerStruggling;
use Ulams\LearnerInsights\Fake\RemediationResponders;
use Ulams\LearnerInsights\Listeners\RecordSignals;
use Ulams\LearnerInsights\Remediation\RemediationService;
use Ulams\LearnerInsights\Scoring\RiskScorer;
use Ulams\LearnerInsights\Scoring\RuleBasedScorer;
use Ulams\LearnerInsights\Services\Eligibility;
use Ulams\LearnerInsights\Services\NudgeService;
use Ulams\LearnerInsights\Services\OutcomeTracker;
use Ulams\LearnerInsights\Services\Privacy;
use Ulams\Scorm\Events\ScormScoCompleted;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\TopicTypeGift\Events\QuizAttemptFinishedEvent;
use Ulams\TopicTypeGift\Events\QuizAttemptStartedEvent;

/**
 * Learner Insights (ADR 0057 to 0062, plan docs/plans/phase-4.md): an append-only stream of
 * learner signals mapped to blueprint elements, which later milestones score with transparent
 * rules, turn into nudges and personal help, and aggregate for authors. Off by default per tenant.
 */
class UlamsLearnerInsightsServiceProvider extends ServiceProvider
{
    public $singletons = [
        Eligibility::class => Eligibility::class,
        RiskScorer::class => RuleBasedScorer::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/learner_insights.php', 'learner_insights');
        $this->app->register(UlamsCourseBuilderServiceProvider::class);
        // AI tasks of this package (the profile of a task is never a model id, ADR 0013)
        $tasks = (array) config('ai.tasks', []);
        $tasks['remediation'] ??= ['profile' => env('AI_TASK_REMEDIATION_PROFILE', 'default'), 'effort' => 'medium', 'max_tokens' => 6000];
        $tasks['reflection_feedback'] ??= ['profile' => env('AI_TASK_REFLECTION_PROFILE', 'light'), 'effort' => 'low', 'max_tokens' => 2000];
        config(['ai.tasks' => $tasks]);

        if (class_exists(UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }
            // key, rules, public, read-only: the tenant admin switches the module and its privacy mode
            AdministrableConfig::registerConfig('learner_insights.enabled', ['boolean'], false, false);
            AdministrableConfig::registerConfig('learner_insights.require_consent', ['boolean'], false, false);
            AdministrableConfig::registerConfig('learner_insights.adaptive_interface', ['boolean'], false, false);
            AdministrableConfig::registerConfig('learner_insights.remediation.holdout_percent', ['integer', 'min:0', 'max:50'], false, false);
            AdministrableConfig::registerConfig('learner_insights.instructors_see_individuals', ['boolean'], false, false);
            AdministrableConfig::registerConfig('learner_insights.nudges.enabled', ['boolean'], false, false);
            AdministrableConfig::registerConfig('learner_insights.nudges.per_course_days', ['integer', 'min:1', 'max:90'], false, false);
            AdministrableConfig::registerConfig('learner_insights.nudges.per_learner_week', ['integer', 'min:1', 'max:14'], false, false);
            AdministrableConfig::registerConfig('learner_insights.retention.signals_days', ['integer', 'min:1', 'max:3650'], false, false);
            // thresholds per rule: `learner_insights.rules.<rule>.<param>`
            foreach ((array) config('learner_insights.rules') as $rule => $params) {
                foreach ($params as $param => $default) {
                    AdministrableConfig::registerConfig("learner_insights.rules.$rule.$param", $param === 'enabled' ? ['boolean'] : ['numeric', 'min:1', 'max:3650'], false, false);
                }
            }
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->app->make(PromptRegistry::class)->addPath('learner-insights', __DIR__ . '/../resources/prompts');
        RemediationResponders::register($this->app->make(FakeResponders::class));
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'learner-insights');

        RateLimiter::for('learner-insights-signals', fn (Request $request) => Limit::perMinute((int) config('learner_insights.client_signals_per_minute'))->by('li-signals:' . ($request->user()?->getKey() ?? $request->ip())));

        Event::listen(CourseStarted::class, [RecordSignals::class, 'courseStarted']);
        Event::listen(CourseFinished::class, [RecordSignals::class, 'courseFinished']);
        Event::listen(TopicFinished::class, [RecordSignals::class, 'topicFinished']);
        Event::listen(TopicProgressUpdated::class, [RecordSignals::class, 'topicProgressed']);
        Event::listen(QuizAttemptStartedEvent::class, [RecordSignals::class, 'quizStarted']);
        Event::listen(QuizAttemptFinishedEvent::class, [RecordSignals::class, 'quizFinished']);
        // the code exercises package is optional: its event is named by string, so nothing is loaded without it
        Event::listen('Ulams\\CodeExercises\\Events\\CodeAttemptFinished', [RecordSignals::class, 'codeFinished']);
        Event::listen(ScormScoCompleted::class, [RecordSignals::class, 'scormCompleted']);
        Event::listen(Login::class, [RecordSignals::class, 'login']);
        H5PUserProgress::saved(fn (H5PUserProgress $p) => $this->app->make(RecordSignals::class)->h5pProgress($p));
        // interventions: an at-risk learner may be nudged; a recovery closes open interventions
        Event::listen(LearnerAtRisk::class, fn (LearnerAtRisk $e) => $this->app->make(NudgeService::class)->consider($e->userId, $e->courseId, $e->reasons));
        Event::listen(LearnerStruggling::class, fn (LearnerStruggling $e) => $this->app->make(RemediationService::class)->onStruggling($e->userId, $e->courseId, $e->elementKey, $e->reasons));
        Event::listen(LearnerRecovered::class, fn (LearnerRecovered $e) => $this->app->make(OutcomeTracker::class)->recovered($e->userId, $e->courseId, $e->elementKey));
        // erasure right: everything stored about a deleted account goes with it
        Event::listen(AccountDeleted::class, fn (AccountDeleted $e) => $this->app->make(Privacy::class)->erase((int) $e->getUser()->getAuthIdentifier(), null, 'account_deleted'));

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillCommand::class, TimelineCommand::class, PruneCommand::class, EvaluateCommand::class, EvalCommand::class, CohortStatsCommand::class, RetireRemediationsCommand::class]);
        }

        // per tenant: `schedule:run` runs for the platform and for every tenant domain
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('learner-insights:prune')->dailyAt('02:40')->withoutOverlapping();
            $schedule->command('learner-insights:cohort-stats')->dailyAt('03:00')->withoutOverlapping();
            $schedule->command('learner-insights:evaluate')->dailyAt('03:30')->withoutOverlapping();
            $schedule->command('learner-insights:retire-remediations')->dailyAt('04:00')->withoutOverlapping();
        });
    }
}
