<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Ulams\Tenancy\Jobs\DeleteTenantJob;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantOperation;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Support\TenantContext;

/** Deletes expired throwaway tenants (preview academies). Scheduled hourly on the platform; safe to run by hand. */
class ReapTenantsCommand extends Command
{
    protected $signature = 'ulams:tenant:reap {--dry-run : List the expired tenants and delete nothing}';

    protected $description = 'Delete throwaway tenants (preview academies) whose time is up';

    public function handle(): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run tenant commands on the platform, without --domain.');

            return self::FAILURE;
        }
        $expired = Tenant::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('expires_at')->get();
        foreach ($expired as $tenant) {
            $busy = TenantOperation::query()->where('tenant_slug', $tenant->slug)->whereIn('status', TenantOperation::ACTIVE)->exists();
            if ($busy) {
                $this->line("  <comment>skip</comment>  {$tenant->slug} (an operation is running)");
                continue;
            }
            $this->line("  <info>reap</info>  {$tenant->slug} (expired {$tenant->expires_at->toIso8601String()})");
            if (!$this->option('dry-run')) {
                $operation = TenantOperation::start(TenantOperation::DELETE, $tenant->slug, TenantLifecycle::DELETE_STEPS, null);
                DeleteTenantJob::dispatch($operation->id);
            }
        }
        $this->info(sprintf('%d expired tenant%s%s.', $expired->count(), $expired->count() === 1 ? '' : 's', $this->option('dry-run') ? ' (dry run)' : ' queued for deletion'));

        return self::SUCCESS;
    }
}
