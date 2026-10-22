<?php

namespace Ulams\AuthoringMetrics;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ulams\AuthoringMetrics\Console\BackfillCommand;
use Ulams\AuthoringMetrics\Listeners\RecordStageTimings;
use Ulams\AuthoringMetrics\Services\StageTimingRecorder;
use Ulams\CourseBuilder\Events\BuilderStageFinished;
use Ulams\CourseBuilder\Events\BuilderStageStarted;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\Tenancy\Upgrade\UpgradeSteps;

/**
 * Authoring time and cost metric (ADR 0194, plan docs/plans/authoring-metric-and-report-builder.md).
 * This milestone (AM-1) records the time of every Course Builder pipeline stage; the Course Builder
 * does not know about this package, it only dispatches events.
 */
class UlamsAuthoringMetricsServiceProvider extends ServiceProvider
{
    public $singletons = [
        StageTimingRecorder::class => StageTimingRecorder::class,
    ];

    public function register(): void
    {
        $this->app->register(UlamsCourseBuilderServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Event::listen(BuilderStageStarted::class, [RecordStageTimings::class, 'started']);
        Event::listen(BuilderStageFinished::class, [RecordStageTimings::class, 'finished']);

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillCommand::class]);
        }

        // builds that ran before this release: timings from the events that still exist, whole-run time for older ones
        if (class_exists(UpgradeSteps::class)) {
            UpgradeSteps::command('authoring_stage_timings_backfill', 'authoring-metrics:backfill', since: '0.3', requiresCommand: true);
        }
    }
}
