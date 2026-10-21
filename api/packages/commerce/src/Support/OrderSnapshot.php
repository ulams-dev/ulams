<?php

namespace Ulams\Commerce\Support;

/**
 * An order as the provider reports it when the LMS asks (not as a webhook claims it). `userRef` is the LMS user id
 * the provider's customer is linked to; null when the customer is unknown, which the LMS treats as a bug.
 */
final class OrderSnapshot
{
    public const STATE_CANCELLED = 'cancelled';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';
    public const PAYMENT_REFUNDED = 'refunded';

    /** @param OrderItemSnapshot[] $items */
    public function __construct(
        public readonly string $orderId,
        public readonly string $channel,
        public readonly ?int $userRef,
        public readonly string $state,
        public readonly string $paymentState,
        public readonly string $currency,
        public readonly int $totalMinor,
        public readonly array $items,
        public readonly ?string $number = null,
        public readonly ?string $token = null,
        public readonly int $taxMinor = 0,
    ) {
    }
}
