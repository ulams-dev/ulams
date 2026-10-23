<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Models\TenantTemplate;
use Ulams\Tenancy\Services\TenantDerivations;
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
        {--theme= : Front theme preset key, coffee, oncall, nightsky, gravity, poland or ulam}
        {--accent= : Accent colour, e.g. #C2552D}
        {--users= : Number of demo students (default: that of the template, else 5)}
        {--template= : Slug of a tenant template: its theme, accent, demo flag and settings are the starting point of a new tenant (the options here win)}
        {--derived-from= : Slug of the tenant this one is created for (a department academy); needs --purpose and --reference, counts against its quota and is recorded}
        {--purpose= : What the tenant is for, e.g. policy_department (with --derived-from)}
        {--reference= : What it is for, e.g. an id in the parent tenant (with --derived-from); asking again with the same reference finds the same tenant}
        {--requested-by= : Who asked, for the record (with --derived-from)}
        {--demo= : Demo mode (DEMO_MODE: login without password, hourly reset): on or off}
        {--db-password= : Password of a database you created yourself (TENANCY_DATABASE_PROVISIONER=manual), at least 16 characters}
        {--redo=* : Step to run again even if recorded as done: database, bucket, storage_identity, env, migrate, passport_keys, passport_client, permissions or demo}';

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
        $derivation = null;
        try {
            if ($this->option('derived-from') !== null && $this->option('derived-from') !== '') {
                $derivation = app(TenantDerivations::class)->register(
                    $slug,
                    (string) $this->option('derived-from'),
                    (string) $this->option('purpose'),
                    (string) $this->option('reference'),
                    $this->option('template') ?: null,
                    $this->option('requested-by') ?: null,
                );
            } elseif ($this->option('purpose') || $this->option('reference')) {
                throw new InvalidArgumentException('--purpose and --reference go with --derived-from.');
            }
            $tenant = $this->resolveTenant($slug);
        } catch (InvalidArgumentException $exception) {
            if ($derivation?->wasRecentlyCreated) {
                $derivation->delete();
            }
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $users = $this->option('users') !== null
            ? (int) $this->option('users')
            : (int) (($this->option('template') ? TenantTemplate::query()->where('slug', $this->option('template'))->value('users') : null) ?? 5);

        $this->info("Provisioning tenant {$tenant->slug} ({$tenant->api_host})");
        try {
            $provisioner->provision($tenant, $users, function (string $step, string $state) {
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
            ['Students', 'student1..' . $users . '@' . TenantNaming::emailDomain($tenant)],
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
            'template' => $this->option('template'),
            'db_password' => $this->option('db-password'),
        ], (array) $this->option('redo'));
    }
}
