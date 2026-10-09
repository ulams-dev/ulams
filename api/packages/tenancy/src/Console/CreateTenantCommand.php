<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Support\StorageOwnership;
use Ulams\Tenancy\Support\TenantContext;
use Ulams\Tenancy\Support\TenantNaming;

class CreateTenantCommand extends Command
{
    protected $signature = 'ulams:tenant:create
        {slug : Lowercase letters and digits, used for hosts, database and bucket names}
        {--name= : Display name (APP_NAME and the global.companyName setting)}
        {--theme= : Front theme preset key, e.g. coffee, oncall, nightsky}
        {--accent= : Accent colour, e.g. #C2552D}
        {--users=5 : Number of demo students}
        {--demo= : Demo mode (DEMO_MODE: login without password, hourly reset): on or off}
        {--redo=* : Step to run again even if recorded as done: database, bucket, env, migrate, passport_keys, passport_client, permissions or demo}';

    protected $description = 'Provision a tenant (database, bucket, env file, migrations, keys, demo users). Safe to re-run: finished steps are skipped.';

    private TenantLifecycle $lifecycle;

    public function handle(TenantProvisioner $provisioner, DomainRegistryContract $domains, TenantLifecycle $lifecycle): int
    {
        $this->lifecycle = $lifecycle;

        if (!TenantContext::isPlatform()) {
            $this->error('Run tenant commands on the platform, without --domain.');

            return self::FAILURE;
        }

        $slug = (string) $this->argument('slug');
        try {
            $tenant = $this->resolveTenant($slug);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Provisioning tenant {$tenant->slug} ({$tenant->api_host})");
        try {
            $provisioner->provision($tenant, (int) $this->option('users'), function (string $step, string $state) {
                match ($state) {
                    'skipped' => $this->line("  <comment>skip</comment>  {$step} (already done)"),
                    'running' => $this->line("  <info>run</info>   {$step}"),
                    default => null,
                };
            });
        } catch (Throwable $exception) {
            $this->error("Step failed: {$tenant->last_error}");
            $this->line('Fix the cause and run the same command again to resume.');

            return self::FAILURE;
        } finally {
            StorageOwnership::handOver($domains->storagePath($tenant->api_host));
        }

        $this->newLine();
        $this->table(['', ''], [
            ['API', $tenant->apiUrl()],
            ['Front', $tenant->frontUrl()],
            ['Admin', $tenant->adminUrl()],
            ['Demo mode', $tenant->demo ? 'on (login without password, reset hourly)' : 'off'],
            ['Admin user', TenantNaming::adminEmail($tenant)],
            ['Tutor', 'tutor@' . TenantNaming::emailDomain($tenant)],
            ['Students', 'student1..' . (int) $this->option('users') . '@' . TenantNaming::emailDomain($tenant)],
            ['Password', 'TENANT_DEMO_PASSWORD (dev only)'],
        ]);

        return self::SUCCESS;
    }

    private function resolveTenant(string $slug): Tenant
    {
        return $this->lifecycle->prepare($slug, [
            'name' => $this->option('name'),
            'theme' => $this->option('theme'),
            'accent' => $this->option('accent'),
            'demo' => $this->option('demo'),
        ], (array) $this->option('redo'));
    }
}
