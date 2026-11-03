<?php

namespace Ulams\Commerce\Support;

/** One line of an order as the provider reports it now. `refunded` = every unit of the line was refunded. */
final class OrderItemSnapshot
{
    public function __construct(
        public readonly string $id,
        public readonly string $productRef,
        public readonly int $quantity = 1,
        public readonly bool $refunded = false,
    ) {
    }
}
