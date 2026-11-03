<?php

namespace Ulams\AuthoringMetrics;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ulams\AuthoringMetrics\Console\BackfillCommand;
use Ulams\AuthoringMetrics\Console\RecomputeCommand;
use Ulams\AuthoringMetrics\Http\Controllers\CourseMetricsController;
use Ulams\AuthoringMetrics\Listeners\RecomputeCourseMetrics;
use Ulams\AuthoringMetrics\Reporting\AuthoringDataset;
use Ulams\AuthoringMetrics\Services\CourseMetrics;
use Ulams\AuthoringMetrics\Services\SubjectResolvers;
use Ulams\AuthoringMetrics\Services\Subjects\BuilderSessionResolver;
use Ulams\AuthoringMetrics\Services\Subjects\BuilderVariantResolver;
use Ulams\AuthoringMetrics\Listeners\RecordStageTimings;
use Ulams\AuthoringMetrics\Services\StageTimingRecorder;
use Ulams\CourseBuilder\Events\BuilderStageFinished;
use Ulams\CourseBuilder\Events\BuilderStageStarted;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\Courses\Events\CoursedPublished;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\UlamsReportingServiceProvider;
use Ulams\Tenancy\Upgrade\UpgradeSteps;

/**
 * Authoring time and cost metric (ADR 0194, plan docs/plans/authoring-metric-and-report-builder.md).
 * Records the time of every Course Builder pipeline stage (AM-1) and keeps a snapshot per course of time, cost and
 * element counts (AM-3). The Course Builder does not know about this package, it only dispatches events.
 */
class UlamsAuthoringMetricsServiceProvider extends ServiceProvider
{
    public $singletons = [
        StageTimingRecorder::class => StageTimingRecorder::class,
        SubjectResolvers::class => SubjectResolvers::class,
        CourseMetrics::class => CourseMetrics::class,
    ];

    public function register(): void
    {
        $this->app->register(UlamsCourseBuilderServiceProvider::class);
        $this->app->register(UlamsReportingServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // the metric is also a report dataset (RB-1)
        $this->app->make(DatasetRegistry::class)->register(new AuthoringDataset());

        Event::listen(BuilderStageStarted::class, [RecordStageTimings::class, 'started']);
        Event::listen(BuilderStageFinished::class, [RecordStageTimings::class, 'finished']);

        // the snapshot follows the course; each package resolves the AI calls of its own subject types
        $resolvers = $this->app->make(SubjectResolvers::class);
        $resolvers->register(new BuilderSessionResolver());
        $resolvers->register(new BuilderVariantResolver());
        Event::listen(BuilderStageFinished::class, [RecomputeCourseMetrics::class, 'runEnded']);
        Event::listen(CoursedPublished::class, [RecomputeCourseMetrics::class, 'courseOf']);
        // Living Course is optional here: its event is named by string and its proposals register their own resolver
        Event::listen('Ulams\\LivingCourse\\Events\\ProposalApplied', [RecomputeCourseMetrics::class, 'courseOf']);

        Route::group(['prefix' => 'api/admin/authoring-metrics', 'middleware' => ['auth:api'], 'where' => ['course' => '[0-9]+']], function () {
            Route::get('courses', [CourseMetricsController::class, 'index']);
            Route::get('courses/{course}', [CourseMetricsController::class, 'show']);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillCommand::class, RecomputeCommand::class]);
        }

        // courses worked on recently; the snapshot also follows each apply, publish and applied proposal
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('authoring-metrics:recompute --active')->dailyAt('03:20')->withoutOverlapping());

        // builds that ran before this release: timings from the events that still exist, whole-run time for older ones
        if (class_exists(UpgradeSteps::class)) {
            UpgradeSteps::command('authoring_stage_timings_backfill', 'authoring-metrics:backfill', since: '0.3', requiresCommand: true);
            // the per-course snapshot of courses that already exist (it follows each apply and publish from here on)
            UpgradeSteps::command('authoring_course_metrics_recompute', 'authoring-metrics:recompute', since: '0.3', requiresCommand: true);
        }
    }
}
