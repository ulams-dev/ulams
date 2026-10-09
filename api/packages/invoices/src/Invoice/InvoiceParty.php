<?php

namespace Ulams\Invoices\Invoice;

/**
 * A seller or a buyer printed on an invoice.
 */
class InvoiceParty
{
    /**
     * @param array<string, string|int|null> $customFields extra "label => value" lines
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly ?string $code = null,
        public readonly ?string $vat = null,
        public readonly ?string $phone = null,
        public readonly array $customFields = [],
    ) {
    }

    /**
     * @param array<string, mixed> $attributes keys: name, address, code, vat, phone, custom_fields
     */
    public static function fromArray(array $attributes): self
    {
        $string = fn (string $key): ?string => isset($attributes[$key]) && $attributes[$key] !== ''
            ? (string) $attributes[$key]
            : null;

        return new self(
            $string('name'),
            $string('address'),
            $string('code'),
            $string('vat'),
            $string('phone'),
            (array) ($attributes['custom_fields'] ?? []),
        );
    }

    /**
     * The seller configured in `invoices.seller.attributes`.
     */
    public static function seller(): self
    {
        return self::fromArray((array) config('invoices.seller.attributes', []));
    }
}
