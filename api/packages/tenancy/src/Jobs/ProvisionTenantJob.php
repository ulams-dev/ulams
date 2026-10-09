<?php

namespace Ulams\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantOperation;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Support\StorageOwnership;

/**
 * Runs the steps of `ulams:tenant:create` for a tenant requested through the platform API and
 * records each one on the operation, so a client can poll it. A failure is kept on the operation
 * and the tenant; asking for the same tenant again resumes at the first unfinished step.
 */
class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $operationId, public readonly int $users = 5)
    {
        $this->routeToLongQueue();
    }

    /** Provisioning outlasts the default queues' retry_after: use the long-job connection (ADR 0083). */
    private function routeToLongQueue(): void
    {
        if ($c = config('queue.long_job.connection')) {
            $this->onConnection($c);
        }
        if ($q = config('queue.long_job.queue')) {
            $this->onQueue($q);
        }
    }

    public function handle(TenantProvisioner $provisioner, DomainRegistryContract $domains): void
    {
        $operation = TenantOperation::query()->findOrFail($this->operationId);
        $tenant = Tenant::query()->firstWhere('slug', $operation->tenant_slug);
        if ($tenant === null) {
            $operation->markFailed("Tenant '{$operation->tenant_slug}' does not exist.");

            return;
        }
        $operation->markRunning();
        try {
            $provisioner->provision($tenant, $this->users, fn (string $step, string $state) => $operation->recordStep($step, $state));
        } catch (Throwable $exception) {
            report($exception);
            $operation->markFailed($tenant->last_error ?: $exception->getMessage());

            return;
        } finally {
            StorageOwnership::handOver($domains->storagePath($tenant->api_host));
        }
        $operation->markSucceeded();
    }
}
