<?php

namespace Ulams\Webhooks\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Ulams\Webhooks\Jobs\DeliverWebhookJob;
use Ulams\Webhooks\Models\WebhookDelivery;
use Ulams\Webhooks\Models\WebhookEndpoint;

/**
 * Turns one domain event into one queued delivery per subscribed, enabled endpoint. The payload is
 * built here and stored with the delivery: what is signed and sent is exactly what the delivery log shows,
 * and a replay sends the same bytes (with a fresh signature timestamp).
 */
class WebhookDispatcher
{
    /**
     * @param array<string,mixed> $object the event's `data.object`
     * @param string|null $dedupeKey events fired more than once for one fact (one per reviewer) carry the same key: an endpoint gets the first only
     * @return int the number of deliveries queued
     */
    public function dispatch(string $type, array $object, ?string $dedupeKey = null): int
    {
        $endpoints = WebhookEndpoint::query()->where('enabled', true)->get()->filter(fn (WebhookEndpoint $e) => $e->subscribesTo($type));

        $eventId = 'evt_' . strtolower((string) Str::ulid());
        $queued = 0;
        foreach ($endpoints as $endpoint) {
            if ($dedupeKey !== null && WebhookDelivery::query()->where('endpoint_id', $endpoint->id)->where('dedupe_key', $dedupeKey)->exists()) {
                continue;
            }
            try {
                $delivery = $this->createDelivery($endpoint, $eventId, $type, $object, false, $dedupeKey);
            } catch (UniqueConstraintViolationException) {
                continue; // a concurrent dispatch of the same event won
            }
            DeliverWebhookJob::enqueue($delivery->id);
            $queued++;
        }

        return $queued;
    }

    /** @param array<string,mixed> $object */
    public function createDelivery(WebhookEndpoint $endpoint, string $eventId, string $type, array $object, bool $test = false, ?string $dedupeKey = null): WebhookDelivery
    {
        return WebhookDelivery::query()->create([
            'endpoint_id' => $endpoint->id,
            'event_id' => $eventId,
            'event_type' => $type,
            'payload' => $this->envelope($endpoint->api_version, $eventId, $type, $object),
            'status' => WebhookDelivery::PENDING,
            'attempts' => 0,
            'is_test' => $test,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    /** @param array<string,mixed> $object */
    private function envelope(string $apiVersion, string $eventId, string $type, array $object): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => $type,
            'api_version' => $apiVersion,
            'created' => time(),
            'data' => ['object' => $object],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
