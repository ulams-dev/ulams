<?php

namespace Ulams\Commerce\Support;

/** A product as the provider holds it now; compared with the LMS side by the nightly drift check. */
final class ProductState
{
    public function __construct(
        public readonly ProductRef $ref,
        public readonly string $name,
        public readonly int $priceMinor,
        public readonly string $currency,
        public readonly bool $enabled,
    ) {
    }
}
