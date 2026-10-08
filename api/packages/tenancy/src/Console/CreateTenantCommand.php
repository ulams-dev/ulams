<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
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
        {--redo=* : Step to run again even if recorded as done: database, bucket, env, migrate, passport_keys, passport_client, permissions or demo}';

    protected $description = 'Provision a tenant (database, bucket, env file, migrations, keys, demo users). Safe to re-run: finished steps are skipped.';

    public function handle(TenantProvisioner $provisioner, DomainRegistryContract $domains): int
    {
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
            ['Admin user', TenantNaming::adminEmail($tenant)],
            ['Tutor', 'tutor@' . TenantNaming::emailDomain($tenant)],
            ['Students', 'student1..' . (int) $this->option('users') . '@' . TenantNaming::emailDomain($tenant)],
            ['Password', 'TENANT_DEMO_PASSWORD (dev only)'],
        ]);

        return self::SUCCESS;
    }

    private function resolveTenant(string $slug): Tenant
    {
        TenantNaming::assertValidSlug($slug);

        foreach (['theme' => '/^[A-Za-z0-9_-]{1,40}$/', 'accent' => '/^#[0-9A-Fa-f]{6}$/'] as $option => $pattern) {
            $value = $this->option($option);
            if ($value !== null && !preg_match($pattern, $value)) {
                throw new InvalidArgumentException("Invalid --{$option} value '{$value}'.");
            }
        }

        $tenant = Tenant::query()->firstWhere('slug', $slug)
            ?? new Tenant(TenantNaming::newTenantAttributes($slug));

        $changes = array_filter([
            'name' => $this->option('name'),
            'theme' => $this->option('theme'),
            'accent' => $this->option('accent'),
        ], fn ($value) => $value !== null && $value !== '');
        $tenant->fill($changes);

        if ($tenant->exists && $tenant->isDirty(['name', 'theme', 'accent'])) {
            // Display name and theme live in the env file and the settings table.
            $tenant->forget('env', 'demo');
        }
        $redo = (array) $this->option('redo');
        $unknown = array_diff($redo, TenantProvisioner::STEPS);
        if ($unknown) {
            throw new InvalidArgumentException('Unknown step(s) in --redo: ' . implode(', ', $unknown));
        }
        $tenant->forget(...$redo);
        $tenant->save();

        return $tenant;
    }
}
