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
use Ulams\Tenancy\Services\TenantLifecycle;

/** Runs `ulams:tenant:delete --force` for a tenant deleted through the platform API. */
class DeleteTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $operationId)
    {
    }

    public function handle(TenantLifecycle $lifecycle): void
    {
        $operation = TenantOperation::query()->findOrFail($this->operationId);
        $tenant = Tenant::query()->firstWhere('slug', $operation->tenant_slug);
        if ($tenant === null) {
            $operation->markFailed("Tenant '{$operation->tenant_slug}' does not exist.");

            return;
        }
        $operation->markRunning();
        try {
            $lifecycle->delete($tenant, fn (string $step) => $operation->recordStep($step, 'running'));
        } catch (Throwable $exception) {
            report($exception);
            $operation->markFailed($exception->getMessage());

            return;
        }
        $operation->markSucceeded();
    }
}
