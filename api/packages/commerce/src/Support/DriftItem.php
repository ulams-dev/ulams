<?php

namespace Ulams\Commerce\Support;

/** One sellable whose product at the provider differs from what the LMS wants to sell. */
final class DriftItem
{
    public const MISSING = 'missing';
    public const PRICE = 'price';
    public const ENABLED = 'enabled';
    public const NAME = 'name';
    public const STALE = 'stale';
    public const ERROR = 'error';
    public const UNREACHABLE = 'unreachable';

    public function __construct(
        public readonly SellableRef $sellable,
        public readonly string $kind,
        public readonly ?string $expected = null,
        public readonly ?string $actual = null,
    ) {
    }

    /** @return array{sellable:string,kind:string,expected:?string,actual:?string} */
    public function toArray(): array
    {
        return ['sellable' => $this->sellable->key(), 'kind' => $this->kind, 'expected' => $this->expected, 'actual' => $this->actual];
    }
}
