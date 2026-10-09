<?php

namespace Ulams\LivingCourse\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Services\ProgressRules;

/** Creates the learner notices of an applied proposal, in chunks of 500 learners. Idempotent: a re-run creates nothing twice. */
class ProgressRulesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(public readonly string $proposalId)
    {
    }

    public static function dispatchFor(string $proposalId): void
    {
        $job = new self($proposalId);
        if ($c = config('living_course.queue_connection')) {
            $job->onConnection($c);
        }
        if ($q = config('living_course.queue')) {
            $job->onQueue($q);
        }
        dispatch($job);
    }

    public function handle(ProgressRules $rules): void
    {
        $proposal = Proposal::query()->find($this->proposalId);
        if ($proposal !== null && $proposal->status === 'applied') {
            $rules->run($proposal);
        }
    }
}
