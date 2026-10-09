<?php

namespace Ulams\LivingCourse\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Services\SourceSync;

/**
 * One check of a connected source. Unique per connection while queued, so a burst of pushes
 * coalesces into one check (webhooks add a debounce delay on top).
 */
class CheckSourceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(public readonly string $connectionId, public readonly string $trigger = 'manual', public readonly ?int $userId = null)
    {
    }

    public function uniqueId(): string
    {
        return $this->connectionId;
    }

    public static function dispatchFor(string $connectionId, string $trigger, ?int $userId = null, int $delaySeconds = 0): void
    {
        $job = new self($connectionId, $trigger, $userId);
        if ($c = config('living_course.queue_connection')) {
            $job->onConnection($c);
        }
        if ($q = config('living_course.queue')) {
            $job->onQueue($q);
        }
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }
        dispatch($job);
    }

    public function handle(SourceSync $sync): void
    {
        $connection = Connection::query()->find($this->connectionId);
        if ($connection !== null && $connection->status !== 'paused') {
            $sync->check($connection, $this->trigger, $this->userId);
        }
    }
}
