<?php

namespace Ulams\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Commerce\Models\OrderEventRecord;
use Ulams\Commerce\Support\OrderEventEnvelope;

/**
 * Stores an order event exactly once per (provider, event id) with `insertOrIgnore`, for the webhook and for
 * reconciliation alike. Returns null for an event the LMS has already seen.
 */
class OrderEventIntake
{
    public const SOURCE_WEBHOOK = 'webhook';
    public const SOURCE_RECONCILE = 'reconcile';

    public function record(string $provider, OrderEventEnvelope $event, string $source, string $payloadSha256): ?OrderEventRecord
    {
        $inserted = DB::table('commerce_order_events')->insertOrIgnore([
            'provider' => $provider,
            'event_id' => $event->eventId,
            'order_id' => $event->orderId,
            'sequence' => $event->sequence,
            'type' => $event->type,
            'source' => $source,
            'payload_sha256' => $payloadSha256,
            'received_at' => now(),
        ]);
        if ($inserted === 0) {
            return null;
        }

        return OrderEventRecord::query()->where('provider', $provider)->where('event_id', $event->eventId)->firstOrFail();
    }
}
