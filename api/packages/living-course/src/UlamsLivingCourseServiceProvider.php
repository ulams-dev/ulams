<?php

namespace Ulams\LivingCourse;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ulams\Ai\Fake\FakeResponders;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\CourseBuilder\Contracts\FragmentArchive;
use Ulams\CourseBuilder\Contracts\RemovalPolicy;
use Ulams\CourseBuilder\Events\ElementPatched;
use Ulams\Courses\Services\Contracts\CourseCompletionGuardContract;
use Ulams\LivingCourse\Events\ProposalApplied;
use Ulams\LivingCourse\Jobs\ProgressRulesJob;
use Ulams\LivingCourse\Progress\LivingCourseAttemptAllowance;
use Ulams\LivingCourse\Progress\LivingCourseCompletionGuard;
use Ulams\LivingCourse\Progress\LivingCourseFragmentArchive;
use Ulams\LivingCourse\Progress\LivingCourseRemovalPolicy;
use Ulams\LivingCourse\Services\ProgressRules;
use Ulams\TopicTypeGift\Events\QuizAttemptFinishedEvent;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptAllowanceContract;
use Ulams\CourseBuilder\Events\SourceIngested;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\CourseBuilder\Services\RunService;
use Ulams\CourseBuilder\Services\SessionState;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\LivingCourse\Console\BackfillCommand;
use Ulams\LivingCourse\Fake\UpdateResponder;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Services\AnalysisService;
use Ulams\LivingCourse\Services\ApplyService;
use Ulams\LivingCourse\Services\AuditLog;
use Ulams\LivingCourse\Services\RevisionService;
use Ulams\LivingCourse\Services\StalenessService;

/**
 * Living Course (ADR 0030 to 0034): a course built with the Course Builder stays connected to its
 * sources. Source revisions and a deterministic fragment diff, impact analysis through citations,
 * AI update proposals the author reviews as one diff, progress-preserving apply, staleness, a
 * hash-chained audit trail, and source connectors (re-upload, Git hosts, web pages, plugins).
 */
class UlamsLivingCourseServiceProvider extends ServiceProvider
{
    public $singletons = [
        AuditLog::class => AuditLog::class,
        RevisionService::class => RevisionService::class,
        StalenessService::class => StalenessService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/living_course.php', 'living_course');
        $this->app->register(UlamsCourseBuilderServiceProvider::class);
        // progress preservation (ADR 0033): the contracts of the builder, courses and GIFT packages
        $this->app->singleton(RemovalPolicy::class, LivingCourseRemovalPolicy::class);
        $this->app->singleton(FragmentArchive::class, LivingCourseFragmentArchive::class);
        $this->app->singleton(CourseCompletionGuardContract::class, LivingCourseCompletionGuard::class);
        $this->app->singleton(QuizAttemptAllowanceContract::class, LivingCourseAttemptAllowance::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        RunService::extend(AnalysisService::HANDLER, fn (Run $run, Session $session) => $this->app->make(AnalysisService::class)->handleRun($run, $session));
        RunService::extend(ApplyService::HANDLER, fn (Run $run, Session $session) => $this->app->make(ApplyService::class)->execute($run, $session));
        RunService::extendRetry(AnalysisService::HANDLER, fn (Step $step) => $this->app->make(AnalysisService::class)->retry($step));
        Llm::extendCost('living-course', fn (Session $session) => ['type' => Proposal::SUBJECT_TYPE, 'ids' => Proposal::query()->where('session_id', $session->id)->pluck('id')->all()]);
        $this->app->make(PromptRegistry::class)->addPath('living-course', __DIR__ . '/../resources/prompts');
        UpdateResponder::register($this->app->make(FakeResponders::class));
        SessionState::extendSummary('living-course', fn (Session $s) => ['freshness' => $this->app->make(StalenessService::class)->summary($s)]);

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillCommand::class]);
        }

        Event::listen(ProposalApplied::class, fn (ProposalApplied $e) => ProgressRulesJob::dispatchFor($e->proposal->id));
        Event::listen(QuizAttemptFinishedEvent::class, fn (QuizAttemptFinishedEvent $e) => $this->app->make(ProgressRules::class)->attemptFinished($e->getAttempt()));

        // an element edited in chat after the analysis: its pending item is out of date
        Event::listen(ElementPatched::class, function (ElementPatched $e) {
            ProposalItem::query()->where('element_id', $e->elementId)->where('status', '!=', 'stale')
                ->whereIn('proposal_id', Proposal::query()->where('session_id', $e->session->id)->whereIn('status', Proposal::OPEN)->select('id'))
                ->whereIn('kind', ['update', 'no_change', 'remove'])->update(['status' => 'stale']);
        });

        // revision 1 of every source of a builder session
        Event::listen(SourceIngested::class, fn (SourceIngested $e) => $this->app->make(RevisionService::class)->ensureInitial($e->source));
    }
}
