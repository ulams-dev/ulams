<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\H5PServiceConfigExporter;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Support\StorageOwnership;
use Ulams\Tenancy\Support\TenantContext;

class SyncTenantEnvCommand extends Command
{
    protected $signature = 'ulams:tenant:sync-env {--migrate : Also run pending migrations for every tenant}';

    protected $description = 'Rebuild .env.<host> files, domain registrations and Passport keys of all tenants from the tenants table';

    public function handle(TenantProvisioner $provisioner, DomainRegistryContract $domains, H5PServiceConfigExporter $h5pConfig): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run tenant commands on the platform, without --domain.');

            return self::FAILURE;
        }

        if (!Schema::hasTable((new Tenant())->getTable())) {
            $this->line('No tenants table yet, nothing to sync.');

            return self::SUCCESS;
        }

        $failed = false;
        try {
            $h5pConfig->exportPlatform();
        } catch (Throwable $exception) {
            $failed = true;
            $this->error("  H5P service config: {$exception->getMessage()}");
        }
        foreach (Tenant::query()->orderBy('slug')->get() as $tenant) {
            try {
                $provisioner->syncRuntime($tenant, (bool) $this->option('migrate'));
                StorageOwnership::handOver($domains->storagePath($tenant->api_host));
                $this->line("  <info>synced</info> {$tenant->api_host}");
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("  {$tenant->api_host}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
