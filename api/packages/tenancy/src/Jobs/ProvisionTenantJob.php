<?php

namespace Ulams\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Support\StorageOwnership;

/**
 * Provisions a tenant in the background. Progress is the tenant row's recorded steps, so a retry
 * (or a second dispatch) resumes at the first unfinished step.
 */
class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(public readonly string $slug, public readonly int $users = 0)
    {
    }

    public function handle(TenantProvisioner $provisioner, DomainRegistryContract $domains): void
    {
        $tenant = Tenant::query()->where('slug', $this->slug)->firstOrFail();
        try {
            $provisioner->provision($tenant, $this->users);
        } finally {
            StorageOwnership::handOver($domains->storagePath($tenant->api_host));
        }
    }

    public function failed(Throwable $exception): void
    {
        // the provisioner already recorded the failed step on the tenant row
    }
}
