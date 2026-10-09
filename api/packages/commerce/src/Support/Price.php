<?php

namespace Ulams\Commerce\Support;

use InvalidArgumentException;

/** An amount in minor units (cents) and an ISO 4217 currency. */
final class Price
{
    public readonly string $currency;

    public function __construct(public readonly int $amountMinor, string $currency)
    {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('A price cannot be negative.');
        }
        $currency = strtoupper($currency);
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('The currency is a three-letter code like USD.');
        }
        $this->currency = $currency;
    }
}
