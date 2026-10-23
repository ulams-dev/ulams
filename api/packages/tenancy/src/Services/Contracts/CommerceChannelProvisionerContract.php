<?php

namespace Ulams\Tenancy\Services\Contracts;

use Ulams\Tenancy\Models\Tenant;

/**
 * Gives one tenant its sales channel at the commerce provider (ADR 0131: one channel per tenant). The tenancy code only
 * knows this contract; the Sylius adapter (`commerce-sylius`) implements it, and without a configured provider the null
 * implementation reports `enabled() === false` and the provisioning step is skipped (and not recorded, so it runs on
 * the first upgrade after commerce is switched on).
 */
interface CommerceChannelProvisionerContract
{
    public function enabled(): bool;

    /**
     * Creates or updates the tenant's channel, idempotently, and points the provider's order events at the tenant API:
     * `$webhookUrl` signed with `$webhookSecret`. Returns the channel code the tenant sells through.
     */
    public function ensure(Tenant $tenant, string $webhookUrl, string $webhookSecret): string;

    /** Takes the tenant's channel off sale when the tenant is deleted. Orders are kept (accounting retention). */
    public function disable(Tenant $tenant): void;
}
