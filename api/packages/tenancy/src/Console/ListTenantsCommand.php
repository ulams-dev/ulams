<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\TenantProvisioner;

class ListTenantsCommand extends Command
{
    protected $signature = 'ulams:tenant:list {--hosts : Print only the API hosts of active tenants, one per line}';

    protected $description = 'List provisioned tenants';

    public function handle(): int
    {
        $tenants = Tenant::query()->orderBy('slug')->get();

        if ($this->option('hosts')) {
            $tenants->where('status', Tenant::STATUS_ACTIVE)->each(fn (Tenant $tenant) => $this->line($tenant->api_host));

            return self::SUCCESS;
        }

        $this->table(
            ['slug', 'name', 'theme', 'demo', 'status', 'api', 'front', 'admin', 'steps'],
            $tenants->map(fn (Tenant $tenant) => [
                $tenant->slug,
                $tenant->name,
                $tenant->theme,
                $tenant->demo ? 'on' : 'off',
                $tenant->status,
                $tenant->api_host,
                $tenant->front_host,
                $tenant->admin_host,
                count($tenant->steps ?? []) . '/' . count(TenantProvisioner::STEPS),
            ])->all()
        );

        return self::SUCCESS;
    }
}
