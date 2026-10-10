<?php

namespace Ulams\CourseBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Pipeline\GlobalEditService;

/** One part of a whole-course edit (the course details, a lesson, the final test). */
class GlobalStepJob implements ShouldQueue
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
        if ($c = config('course_builder.queue_connection')) {
            $job->onConnection($c);
        }
        if ($q = config('course_builder.queue')) {
            $job->onQueue($q);
        }
        dispatch($job);
    }

    public function handle(GlobalEditService $global): void
    {
        $step = Step::query()->find($this->stepId);
        if ($step !== null) {
            $global->runStep($step);
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
