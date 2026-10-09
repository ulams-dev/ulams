<?php

namespace Ulams\Commerce\Support;

/** A product in a provider's catalogue. */
final class ProductRef
{
    public function __construct(public readonly string $provider, public readonly string $externalId)
    {
    }
}
