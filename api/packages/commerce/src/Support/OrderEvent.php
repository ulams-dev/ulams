<?php

namespace Ulams\Commerce\Support;

/**
 * A verified order event from a provider. `idempotencyKey` is unique per provider event, so a
 * redelivered webhook never grants access twice.
 */
final class OrderEvent
{
    public const PAID = 'paid';
    public const REFUNDED = 'refunded';
    public const CANCELLED = 'cancelled';

    public function __construct(
        public readonly string $provider,
        public readonly string $type,
        public readonly string $idempotencyKey,
        public readonly string $orderId,
        public readonly ProductRef $product,
        public readonly string $buyerEmail,
    ) {
    }
}
