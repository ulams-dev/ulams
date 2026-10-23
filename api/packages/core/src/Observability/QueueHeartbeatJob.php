<?php

namespace Ulams\Core\Observability;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Written by a queue worker: proves that jobs are picked up, not only that they can be pushed. */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function handle(): void
    {
        Heartbeat::beat(Heartbeat::QUEUE);
    }
}
