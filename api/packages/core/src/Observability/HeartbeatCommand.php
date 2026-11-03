<?php

namespace Ulams\Core\Observability;

use Illuminate\Console\Command;

/**
 * Scheduled every minute: records that the scheduler runs and sends a job through the queue that
 * records that a worker runs (the `queue` and `scheduler` health checks, `ulams_heartbeat_age_seconds`).
 */
class HeartbeatCommand extends Command
{
    protected $signature = 'ulams:heartbeat';

    protected $description = 'Record the scheduler heartbeat and queue a job that records the queue heartbeat';

    public function handle(): int
    {
        Heartbeat::beat(Heartbeat::SCHEDULER);
        QueueHeartbeatJob::dispatch();

        return self::SUCCESS;
    }
}
