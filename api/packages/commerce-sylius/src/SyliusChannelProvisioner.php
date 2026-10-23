<?php

namespace Ulams\CommerceSylius;

use Ulams\Commerce\Support\ChannelSpec;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\CommerceChannelProvisionerContract;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\CommerceSylius\Http\SyliusClient;

/**
 * Gives a tenant its Sylius channel when the tenant is provisioned, and again from the upgrade step for tenants that
 * existed before commerce was switched on (ADR 0131, plan section 8). The channel code is the tenant slug.
 *
 * Defaults that a tenant may override once (`COMMERCE_CURRENCY`, `COMMERCE_LOCALE` through `ulams:tenant:set-env`; the
 * base currency cannot change afterwards): the locale follows the platform language, the currency is PLN for Polish and
 * EUR for every other language (plan Q2). It sells to the 27 EU countries plus `COMMERCE_EXTRA_COUNTRIES`, and the seller
 * country's tax zone (`COMMERCE_SELLER_COUNTRY`) applies when the billing address does not decide.
 */
class SyliusChannelProvisioner implements CommerceChannelProvisionerContract
{
    private const LOCALES = ['en' => 'en_US', 'pl' => 'pl_PL', 'de' => 'de_DE', 'fr' => 'fr_FR', 'es' => 'es_ES', 'it' => 'it_IT', 'nl' => 'nl_NL', 'pt' => 'pt_PT', 'cs' => 'cs_CZ', 'sk' => 'sk_SK', 'uk' => 'uk_UA', 'zh' => 'zh_CN'];

    public function __construct(private readonly SyliusProvider $provider, private readonly SyliusClient $client)
    {
    }

    public function enabled(): bool
    {
        return config('commerce.provider') === SyliusProvider::KEY && $this->client->configured();
    }

    public function spec(Tenant $tenant, string $webhookUrl, string $webhookSecret): ChannelSpec
    {
        $overrides = TenantNaming::inheritedValues($tenant);
        $language = strtolower(substr((string) config('app.locale', 'en'), 0, 2));
        $locale = $overrides['COMMERCE_LOCALE'] ?? self::LOCALES[$language] ?? 'en_US';
        $currency = strtoupper((string) ($overrides['COMMERCE_CURRENCY'] ?? config('commerce-sylius.default_currency') ?: ($language === 'pl' ? 'PLN' : 'EUR')));
        $extra = array_values(array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', (string) config('commerce-sylius.extra_countries')))));

        return new ChannelSpec(
            $tenant->slug, $tenant->name, $currency, $locale, [...(new EuVatSeeder($this->client))->countries(), ...$extra],
            strtoupper((string) config('commerce-sylius.seller_country', 'PL')), $tenant->front_host, $webhookUrl, $webhookSecret,
        );
    }

    public function ensure(Tenant $tenant, string $webhookUrl, string $webhookSecret): string
    {
        return $this->provider->ensureChannel($this->spec($tenant, $webhookUrl, $webhookSecret))->code;
    }

    public function disable(Tenant $tenant): void
    {
        $this->provider->disableChannel($tenant->commerce_channel ?? $tenant->slug);
    }
}
