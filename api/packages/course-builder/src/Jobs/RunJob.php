<?php

namespace Ulams\CourseBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Services\RunService;

/**
 * Executes one run (ingest, interview, outline, generate, patch, apply) on the builder queue.
 * One try: the SDK already retries transient HTTP errors, and a second LLM attempt would spend
 * twice; a failed run is retried by the author.
 */
class RunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $runId)
    {
    }

    public static function dispatchFor(string $runId): void
    {
        $job = new self($runId);
        if ($c = config('course_builder.queue_connection')) {
            $job->onConnection($c);
        }
        if ($q = config('course_builder.queue')) {
            $job->onQueue($q);
        }
        dispatch($job);
    }

    public function handle(RunService $runs): void
    {
        $run = Run::query()->find($this->runId);
        if ($run !== null) {
            $runs->execute($run);
        }
    }

    public function failed(Throwable $e): void
    {
        $run = Run::query()->find($this->runId);
        if ($run !== null) {
            app(RunService::class)->fail($run, 'Something went wrong in this step. Try again; if it keeps failing, contact your admin.', $e);
        }
    }
}
