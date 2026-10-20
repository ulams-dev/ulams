<?php

namespace Ulams\Webhooks\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Ulams\Webhooks\Models\WebhookDelivery;
use Ulams\Webhooks\Services\DeliveryRunner;

/**
 * One attempt of one delivery. Retries are new delayed jobs scheduled by the runner (exponential
 * backoff), so a worker is never held while waiting, and a lost job is picked up by `webhooks:retry-due`.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly string $deliveryId)
    {
    }

    /** Queue the next attempt after `$delaySeconds` on the configured connection and queue. */
    public static function enqueue(string $deliveryId, int $delaySeconds = 0): void
    {
        $job = new self($deliveryId);
        if ($connection = config('ulams_webhooks.queue_connection')) {
            $job->onConnection($connection);
        }
        if ($queue = config('ulams_webhooks.queue')) {
            $job->onQueue($queue);
        }
        // the delivery row may still be in an open transaction of the caller: the job waits for it (see handle())
        $job->beforeCommit();
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }
        dispatch($job);
    }

    /** The synchronous queue ignores delays; retries then wait for the scheduler instead of blocking a request. */
    public static function delaysSupported(): bool
    {
        $connection = config('ulams_webhooks.queue_connection') ?: config('queue.default');

        return $connection !== 'sync';
    }

    public function handle(DeliveryRunner $runner): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);
        if ($delivery === null) {
            // queued from inside a transaction that has not committed yet
            $this->release(10);

            return;
        }
        $runner->attempt($delivery);
    }
}
