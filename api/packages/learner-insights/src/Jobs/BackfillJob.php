<?php

namespace Ulams\LearnerInsights\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/** `learner-insights:backfill` off the request (POST /api/admin/learner-insights/backfill). */
class BackfillJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public readonly ?int $courseId = null)
    {
        // up to 30 minutes: the long Course Builder queue, whose retry_after is above the timeout (ADR 0083)
        $this->onConnection(config('learner_insights.ai_queue_connection'));
        $this->onQueue(config('learner_insights.ai_queue'));
    }

    public function handle(): void
    {
        Artisan::call('learner-insights:backfill', array_filter(['--course' => $this->courseId]));
    }
}
