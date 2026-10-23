<?php

namespace Ulams\Tenancy\Services;

use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\CommerceChannelProvisionerContract;

/** No commerce provider with channels is configured: tenants have no channel. */
class NullCommerceChannelProvisioner implements CommerceChannelProvisionerContract
{
    public function enabled(): bool
    {
        return false;
    }

    public function ensure(Tenant $tenant, string $webhookUrl, string $webhookSecret): string
    {
        throw new \LogicException('No commerce provider with channels is configured (COMMERCE_PROVIDER=sylius).');
    }

    public function disable(Tenant $tenant): void
    {
    }
}
