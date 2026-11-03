<?php

namespace Ulams\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Commerce\Models\OrderEventRecord;
use Ulams\Commerce\Support\OrderEventEnvelope;

/**
 * Class OrderEventIntake
 *
 * This class is responsible for storing order events exactly once per (provider, event id) using the `insertOrIgnore`
 * method. This is useful for both webhook and reconciliation processes. If an event has already been seen by the LMS,
 * the method returns null.
 */
class OrderEventIntake
{
    /**
     * Constant representing the source of the event as a webhook.
     */
    public const SOURCE_WEBHOOK = 'webhook';

    /**
     * Constant representing the source of the event as a reconciliation process.
     */
    public const SOURCE_RECONCILE = 'reconcile';

    /**
     * Records an order event in the database.
     *
     * @param string $provider The provider of the order event.
     * @param OrderEventEnvelope $event The order event envelope containing event details.
     * @param string $source The source of the event (either SOURCE_WEBHOOK or SOURCE_RECONCILE).
     * @param string $payloadSha256 The SHA-256 hash of the event payload.
     *
     * @return OrderEventRecord|null The recorded order event record if inserted, otherwise null if the event was already recorded.
     */
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
