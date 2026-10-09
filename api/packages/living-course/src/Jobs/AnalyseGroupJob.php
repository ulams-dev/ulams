<?php

namespace Ulams\LivingCourse\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Ulams\CourseBuilder\Models\Step;
use Ulams\LivingCourse\Services\AnalysisService;

/** One group of an update proposal analysis (one `update` call). One try: a failed step is retried alone by the author. */
class AnalyseGroupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $stepId)
    {
    }

    public static function dispatchFor(string $stepId): void
    {
        $job = new self($stepId);
        if ($c = config('living_course.queue_connection')) {
            $job->onConnection($c);
        }
        if ($q = config('living_course.queue')) {
            $job->onQueue($q);
        }
        dispatch($job);
    }

    public function handle(AnalysisService $analysis): void
    {
        $step = Step::query()->find($this->stepId);
        if ($step !== null) {
            $analysis->runStep($step);
        }
    }

    public function failed(Throwable $e): void
    {
        $step = Step::query()->find($this->stepId);
        if ($step !== null && $step->status !== 'done') {
            $step->forceFill(['status' => 'failed', 'error' => 'The step stopped unexpectedly. Retry it.'])->save();
            $step->run->forceFill(['status' => 'needs_attention'])->save();
            report($e);
        }
    }
}
