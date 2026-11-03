<?php

namespace Ulams\Commerce\Support;

use InvalidArgumentException;

/**
 * What a provider needs to know to sell a sellable (interface v2, ADR 0130): the buyer-facing name, the price,
 * whether buyers may see it and, for an access pass, how many days of access it grants. The LMS builds it from
 * its own entities; a provider never reads LMS tables.
 */
final class ProductSpec
{
    public function __construct(
        public readonly string $name,
        public readonly Price $price,
        public readonly bool $active,
        public readonly ?string $description = null,
        public readonly ?int $accessDays = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A product needs a name.');
        }
        if ($accessDays !== null && $accessDays < 1) {
            throw new InvalidArgumentException('An access pass lasts at least one day.');
        }
    }

    public function withActive(bool $active): self
    {
        return new self($this->name, $this->price, $active, $this->description, $this->accessDays);
    }

    /** Stable fingerprint of what is sent to the provider; unchanged specs are not synced again. */
    public function hash(): string
    {
        return hash('sha256', json_encode([$this->name, $this->price->amountMinor, $this->price->currency, $this->active, $this->description, $this->accessDays], JSON_THROW_ON_ERROR));
    }
}
