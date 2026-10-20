<?php

namespace Ulams\Webhooks\Services;

use Throwable;
use Ulams\Core\Http\SafeHttp;
use Ulams\Webhooks\Jobs\DeliverWebhookJob;
use Ulams\Webhooks\Models\WebhookAttempt;
use Ulams\Webhooks\Models\WebhookDelivery;
use Ulams\Webhooks\Models\WebhookEndpoint;
use Ulams\Webhooks\Support\WebhookSignature;

/**
 * Performs one HTTP attempt of a delivery and decides what happens next. The request goes through
 * the shared SSRF-safe client (https only, public addresses only, the checked address pinned for the
 * connection, no redirects), so a URL that was fine when registered cannot be turned against the
 * internal network later (DNS rebinding). 2xx is success; anything else, including no answer, is retried
 * with exponential backoff until `max_attempts`, then the delivery is failed.
 */
class DeliveryRunner
{
    public function attempt(WebhookDelivery $delivery): void
    {
        $endpoint = $delivery->endpoint;
        $replay = $delivery->replay_of !== null;
        if ($endpoint === null || (!$endpoint->enabled && !$delivery->is_test && !$replay)) {
            $this->settle($delivery, WebhookDelivery::CANCELLED);

            return;
        }
        if (!$this->claim($delivery)) {
            return;
        }
        $number = $delivery->attempts;

        $started = microtime(true);
        $status = null;
        $body = null;
        $error = null;
        try {
            [$status, $body] = $this->send($endpoint, $delivery, $number);
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
        }
        WebhookAttempt::query()->create([
            'delivery_id' => $delivery->id,
            'number' => $number,
            'status_code' => $status,
            'response_body' => $body,
            'error' => $error,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        $delivery->forceFill(['last_status_code' => $status])->save();
        if ($status !== null && $status >= 200 && $status < 300) {
            $this->succeeded($delivery, $endpoint);

            return;
        }
        $this->failed($delivery, $endpoint, $number);
    }

    /** Re-dispatch deliveries whose retry is overdue (a lost job, or the synchronous queue). */
    public function retryDue(int $graceSeconds): int
    {
        $due = WebhookDelivery::query()->where('status', WebhookDelivery::RETRYING)
            ->where('next_attempt_at', '<=', now()->subSeconds($graceSeconds))->limit(500)->pluck('id');
        foreach ($due as $id) {
            DeliverWebhookJob::enqueue($id);
        }

        return $due->count();
    }

    /** One worker wins the attempt: the attempt counter moves only if nobody else moved it first. */
    private function claim(WebhookDelivery $delivery): bool
    {
        $claimed = WebhookDelivery::query()->whereKey($delivery->id)->where('attempts', (int) $delivery->attempts)
            ->whereIn('status', [WebhookDelivery::PENDING, WebhookDelivery::RETRYING])
            ->update(['attempts' => (int) $delivery->attempts + 1, 'next_attempt_at' => null]);
        if ($claimed !== 1) {
            return false;
        }
        $delivery->refresh();

        return true;
    }

    /** @return array{0:int,1:string} */
    private function send(WebhookEndpoint $endpoint, WebhookDelivery $delivery, int $attempt): array
    {
        $handler = app()->bound('webhooks.http_handler') ? app('webhooks.http_handler') : null;
        $client = SafeHttp::client($handler, [
            'label' => 'Webhook URLs',
            'allow_insecure' => (bool) config('ulams_webhooks.allow_insecure'),
            'allowed_hosts' => (array) config('ulams_webhooks.allowed_hosts'),
            'max_bytes' => 1048576,
            'timeout' => (int) config('ulams_webhooks.timeout'),
            'connect_timeout' => (int) config('ulams_webhooks.connect_timeout'),
        ]);
        $response = $client->post($endpoint->url, [
            'body' => $delivery->payload,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Ulams-Webhooks/1',
                WebhookSignature::HEADER => WebhookSignature::sign($endpoint->secret, $delivery->payload),
                'Ulams-Event-Id' => $delivery->event_id,
                'Ulams-Event-Type' => $delivery->event_type,
                'Ulams-Delivery-Id' => $delivery->id,
                'Ulams-Delivery-Attempt' => (string) $attempt,
                'Ulams-Api-Version' => $endpoint->api_version,
            ],
        ]);

        return [$response->getStatusCode(), mb_substr((string) $response->getBody(), 0, (int) config('ulams_webhooks.response_body_bytes'))];
    }

    private function succeeded(WebhookDelivery $delivery, WebhookEndpoint $endpoint): void
    {
        $delivery->forceFill(['status' => WebhookDelivery::SUCCEEDED, 'delivered_at' => now(), 'next_attempt_at' => null])->save();
        $endpoint->forceFill(['consecutive_failures' => 0, 'last_delivery_at' => now()])->save();
    }

    private function failed(WebhookDelivery $delivery, WebhookEndpoint $endpoint, int $number): void
    {
        $max = $delivery->is_test ? 1 : (int) config('ulams_webhooks.max_attempts');
        if ($number < $max) {
            $backoff = (array) config('ulams_webhooks.backoff_seconds');
            $wait = (int) ($backoff[min($number, count($backoff)) - 1] ?? 60);
            $delivery->forceFill(['status' => WebhookDelivery::RETRYING, 'next_attempt_at' => now()->addSeconds($wait)])->save();
            if (DeliverWebhookJob::delaysSupported()) {
                DeliverWebhookJob::enqueue($delivery->id, $wait);
            }

            return;
        }

        $delivery->forceFill(['status' => WebhookDelivery::FAILED, 'next_attempt_at' => null])->save();
        $endpoint->forceFill(['last_delivery_at' => now()])->save();
        if ($delivery->is_test) {
            return;
        }
        $endpoint->increment('consecutive_failures');
        if ($endpoint->enabled && $endpoint->consecutive_failures >= (int) config('ulams_webhooks.disable_after_failures')) {
            $endpoint->forceFill([
                'enabled' => false,
                'disabled_reason' => 'Disabled after ' . $endpoint->consecutive_failures . ' failed deliveries in a row.',
            ])->save();
        }
    }

    private function settle(WebhookDelivery $delivery, string $status): void
    {
        if (in_array($delivery->status, [WebhookDelivery::PENDING, WebhookDelivery::RETRYING], true)) {
            $delivery->forceFill(['status' => $status, 'next_attempt_at' => null])->save();
        }
    }
}
