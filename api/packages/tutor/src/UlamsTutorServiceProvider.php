<?php

namespace Ulams\Tutor;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Ulams\Ai\Fake\FakeResponders;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\Auth\Events\AccountDeleted;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Tutor\Console\IndexCommand;
use Ulams\Tutor\Console\PruneCommand;
use Ulams\Tutor\Fake\TutorResponder;
use Ulams\Tutor\Retrieval\LikeRetriever;
use Ulams\Tutor\Retrieval\PostgresRetriever;
use Ulams\Tutor\Retrieval\TutorRetriever;

/**
 * The course tutor (ADR 0060, plan docs/plans/phase-4.md section 8): answers a learner's question only
 * from the course's own content, with citations, says when something is outside the course and never
 * gives quiz answers while an attempt is open. Off by default per tenant.
 */
class UlamsTutorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tutor.php', 'tutor');
        $this->app->register(UlamsCourseBuilderServiceProvider::class);
        $this->app->bind(TutorRetriever::class, fn () => DB::getDriverName() === 'pgsql' ? new PostgresRetriever() : new LikeRetriever());

        // the profile of the task is never a model id (ADR 0013)
        $tasks = (array) config('ai.tasks', []);
        $tasks['tutor'] ??= ['profile' => env('AI_TASK_TUTOR_PROFILE', 'light'), 'effort' => 'low', 'max_tokens' => 2000];
        config(['ai.tasks' => $tasks]);

        if (class_exists(UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }
            AdministrableConfig::registerConfig('tutor.enabled', ['boolean'], false, false);
            AdministrableConfig::registerConfig('tutor.limits.daily_questions', ['integer', 'min:1', 'max:1000'], false, false);
            AdministrableConfig::registerConfig('tutor.retention_days', ['integer', 'min:1', 'max:3650'], false, false);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->app->make(PromptRegistry::class)->addPath('tutor', __DIR__ . '/../resources/prompts');
        TutorResponder::register($this->app->make(FakeResponders::class));
        RateLimiter::for('tutor', fn (Request $request) => Limit::perMinute((int) config('tutor.limits.per_minute'))->by('tutor:' . ($request->user()?->getKey() ?? $request->ip())));

        // erasure right: a deleted account takes its conversations with it
        $this->app['events']->listen(AccountDeleted::class, function (AccountDeleted $e) {
            $id = (int) $e->getUser()->getAuthIdentifier();
            $ids = DB::table('tutor_conversations')->where('user_id', $id)->pluck('id');
            DB::table('tutor_messages')->whereIn('conversation_id', $ids)->delete();
            DB::table('tutor_conversations')->where('user_id', $id)->delete();
            DB::table('tutor_daily_usage')->where('user_id', $id)->delete();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([IndexCommand::class, PruneCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('tutor:prune')->dailyAt('02:50')->withoutOverlapping());
    }
}
