<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Support\TenantContext;

/**
 * Creates (or repairs) the commerce channel of tenants that were provisioned before commerce was switched on, or whose
 * channel was lost. Idempotent; also runs as an upgrade step. Does nothing without a provider that has channels.
 */
class CommerceChannelCommand extends Command
{
    protected $signature = 'ulams:tenant:commerce-channel {slug? : One tenant; default every active tenant}';

    protected $description = 'Create the commerce channel (and webhook link) of tenants that have none';

    public function handle(TenantProvisioner $provisioner): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run tenant commands on the platform, without --domain.');

            return self::FAILURE;
        }
        if (!$provisioner->commerceEnabled()) {
            $this->line('No commerce provider with channels is configured (COMMERCE_PROVIDER=sylius); nothing to do.');

            return self::SUCCESS;
        }
        $query = Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->orderBy('slug');
        if ($this->argument('slug')) {
            $query->where('slug', (string) $this->argument('slug'));
        }
        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn($this->argument('slug') ? "No active tenant \"{$this->argument('slug')}\"." : 'No active tenants.');

            return $this->argument('slug') ? self::FAILURE : self::SUCCESS;
        }
        $failed = false;
        foreach ($tenants as $tenant) {
            try {
                $this->line("  <info>channel {$provisioner->ensureCommerceChannel($tenant)}</info> {$tenant->api_host}");
            } catch (Throwable $e) {
                $failed = true;
                $this->error("  {$tenant->api_host}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
