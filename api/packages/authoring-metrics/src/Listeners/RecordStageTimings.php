<?php

namespace Ulams\AuthoringMetrics\Listeners;

use Illuminate\Support\Facades\Log;
use Ulams\AuthoringMetrics\Services\StageTimingRecorder;
use Ulams\CourseBuilder\Events\BuilderStageFinished;
use Ulams\CourseBuilder\Events\BuilderStageStarted;
use Ulams\CourseBuilder\Models\Run;

/**
 * Measuring must never break a build: a failure here is logged and the pipeline carries on.
 */
class RecordStageTimings
{
    public function __construct(private readonly StageTimingRecorder $recorder)
    {
    }

    public function started(BuilderStageStarted $event): void
    {
        if ($event->stage === null) {
            return; // the run start is read from its AG-UI event when the run ends
        }
        $this->guard(fn () => $this->recorder->stageStarted($event->sessionId, $event->runId, $event->runKind, $event->stage, $event->at));
    }

    public function finished(BuilderStageFinished $event): void
    {
        $this->guard(function () use ($event) {
            if ($event->stage !== null) {
                $this->recorder->stageFinished($event->runId, $event->stage, $event->at);

                return;
            }
            $run = Run::query()->find($event->runId);
            if ($run !== null) {
                $this->recorder->runEnded($run, $event->at, $event->outcome === 'finished');
            }
        });
    }

    private function guard(\Closure $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            Log::warning('authoring-metrics: stage timing not recorded', ['error' => $e->getMessage()]);
        }
    }
}
