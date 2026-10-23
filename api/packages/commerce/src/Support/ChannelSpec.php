<?php

namespace Ulams\Commerce\Support;

use InvalidArgumentException;

/**
 * What one tenant's sales channel at the provider must look like (ADR 0131): its code (the tenant slug), the currency it
 * sells in (fixed at creation), the locale, the countries it may sell to and the seller country whose tax zone applies
 * when a buyer's address does not decide. `webhookUrl` and `webhookSecret` tell the provider where and how to sign the
 * order events of this channel.
 */
final class ChannelSpec
{
    /** @param list<string> $countries ISO 3166-1 alpha-2 codes */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $currency,
        public readonly string $locale,
        public readonly array $countries,
        public readonly string $sellerCountry,
        public readonly ?string $hostname = null,
        public readonly ?string $webhookUrl = null,
        public readonly ?string $webhookSecret = null,
    ) {
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $code)) {
            throw new InvalidArgumentException('A channel code is 1 to 64 letters, digits, - or _.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('The channel currency is a three-letter code like EUR.');
        }
        if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
            throw new InvalidArgumentException('The channel locale looks like en_US.');
        }
        foreach ([...$countries, $sellerCountry] as $country) {
            if (!preg_match('/^[A-Z]{2}$/', $country)) {
                throw new InvalidArgumentException("\"{$country}\" is not a country code.");
            }
        }
        if (($webhookUrl === null) !== ($webhookSecret === null)) {
            throw new InvalidArgumentException('A webhook needs both a URL and a secret.');
        }
    }
}
