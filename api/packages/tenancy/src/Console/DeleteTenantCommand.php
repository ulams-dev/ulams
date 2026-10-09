<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Support\TenantContext;

class DeleteTenantCommand extends Command
{
    protected $signature = 'ulams:tenant:delete {slug} {--force : Required. Drops the database, role, bucket objects, env file and storage}';

    protected $description = 'Delete a tenant and all its data';

    public function handle(TenantLifecycle $lifecycle): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run tenant commands on the platform, without --domain.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->firstWhere('slug', $this->argument('slug'));
        if (!$tenant) {
            $this->error("Tenant '{$this->argument('slug')}' does not exist.");

            return self::FAILURE;
        }
        if (!$this->option('force')) {
            $this->error("This permanently deletes all data of '{$tenant->slug}'. Re-run with --force.");

            return self::FAILURE;
        }

        try {
            $lifecycle->delete($tenant, fn (string $step) => $this->line("  <info>remove</info> {$step}" . ($step === 'redis' ? ' keys' : '')));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info("Deleted tenant {$tenant->slug}.");

        return self::SUCCESS;
    }
}
