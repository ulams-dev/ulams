<?php

namespace Ulams\Commerce\Support;

/** Where the buyer continues to pay. */
final class CheckoutRef
{
    public function __construct(public readonly string $url, public readonly ?string $externalId = null)
    {
    }
}
