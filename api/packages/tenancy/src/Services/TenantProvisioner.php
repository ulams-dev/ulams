<?php

namespace Ulams\Tenancy\Services;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Support\PassportKeyPermissions;
use Ulams\Tenancy\Support\TenantNaming;

/**
 * Provisions a tenant step by step. Every finished step is recorded on the tenant row, so a
 * failed run resumes from the first unfinished step when the command is run again.
 */
class TenantProvisioner
{
    public const STEPS = [
        'database',
        'bucket',
        'env',
        'migrate',
        'passport_keys',
        'passport_client',
        'permissions',
        'demo',
    ];

    private const PRIVATE_KEY = 'oauth-private.key';
    private const PUBLIC_KEY = 'oauth-public.key';

    public function __construct(
        private DatabaseProvisionerContract $database,
        private BucketProvisionerContract $buckets,
        private DomainRegistryContract $domains,
        private TenantCommandRunnerContract $runner,
        private Filesystem $files,
    ) {
    }

    /**
     * @param Closure(string $step, string $state): void|null $report receives (step, 'skipped'|'running'|'done')
     */
    public function provision(Tenant $tenant, int $users = 5, ?Closure $report = null): Tenant
    {
        $report ??= fn () => null;

        if ($tenant->status !== Tenant::STATUS_PROVISIONING) {
            $tenant->status = Tenant::STATUS_PROVISIONING;
            $tenant->last_error = null;
            $tenant->save();
        }

        foreach (self::STEPS as $step) {
            if ($tenant->hasCompleted($step)) {
                $report($step, 'skipped');
                continue;
            }

            $report($step, 'running');
            try {
                $this->runStep($tenant, $step, $users);
            } catch (Throwable $exception) {
                $tenant->status = Tenant::STATUS_FAILED;
                $tenant->last_error = "[{$step}] " . $exception->getMessage();
                $tenant->save();

                throw $exception;
            }
            $tenant->markCompleted($step);
            $report($step, 'done');
        }

        $tenant->status = Tenant::STATUS_ACTIVE;
        $tenant->last_error = null;
        $tenant->save();

        return $tenant;
    }

    /**
     * Rebuilds the runtime state that is not stored in a database (env file, registration,
     * passport keys) for an already provisioned tenant, e.g. on a fresh container.
     */
    public function syncRuntime(Tenant $tenant, bool $migrate = false): void
    {
        if (!$tenant->hasCompleted('env')) {
            return;
        }

        $this->domains->add($tenant->api_host, TenantNaming::envValues($tenant));
        $this->restorePassportKeys($tenant);

        if ($migrate && $tenant->hasCompleted('migrate')) {
            $this->runner->run($tenant->api_host, ['migrate', '--force']);
        }
    }

    public function deprovision(Tenant $tenant, ?Closure $report = null): void
    {
        $report ??= fn () => null;

        $report('database', 'running');
        $this->database->drop($tenant->db_name, $tenant->db_user);
        $report('bucket', 'running');
        $this->buckets->delete($tenant->bucket);
        $report('env', 'running');
        $this->domains->remove($tenant->api_host);
    }

    private function runStep(Tenant $tenant, string $step, int $users): void
    {
        $host = $tenant->api_host;

        match ($step) {
            'database' => $this->database->ensure($tenant->db_name, $tenant->db_user, $tenant->db_password),
            'bucket' => $this->buckets->ensure($tenant->bucket),
            'env' => $this->domains->add($host, TenantNaming::envValues($tenant)),
            'migrate' => $this->runner->run($host, ['migrate', '--force']),
            'passport_keys' => $this->passportKeys($tenant),
            'passport_client' => $this->runner->run($host, [
                'passport:client',
                '--personal',
                '--name=' . $tenant->name . ' Personal Access Client',
            ]),
            'permissions' => $this->runner->run($host, ['db:seed', '--class=PermissionsSeeder', '--force']),
            'demo' => $this->runner->run($host, array_values(array_filter([
                'ulams:tenant:seed-demo',
                '--users=' . max(0, $users),
                '--name=' . $tenant->name,
                $tenant->theme ? '--theme=' . $tenant->theme : null,
                $tenant->accent ? '--accent=' . $tenant->accent : null,
                '--front-url=' . $tenant->frontUrl(),
                '--email-domain=' . TenantNaming::emailDomain($tenant),
            ]))),
        };
    }

    /**
     * Generates the tenant's own Passport key pair (tokens of one tenant are rejected by
     * every other) and keeps an encrypted copy on the tenant row so it can be restored.
     */
    private function passportKeys(Tenant $tenant): void
    {
        if ($this->restorePassportKeys($tenant)) {
            return;
        }

        $this->runner->run($tenant->api_host, ['passport:keys', '--force']);

        $directory = $this->domains->storagePath($tenant->api_host);
        PassportKeyPermissions::apply($directory);
        $tenant->passport_private_key = $this->files->get($directory . '/' . self::PRIVATE_KEY);
        $tenant->passport_public_key = $this->files->get($directory . '/' . self::PUBLIC_KEY);
        $tenant->save();
    }

    private function restorePassportKeys(Tenant $tenant): bool
    {
        if (!$tenant->passport_private_key || !$tenant->passport_public_key) {
            return false;
        }

        $directory = $this->domains->storagePath($tenant->api_host);
        $this->files->ensureDirectoryExists($directory);
        foreach ([self::PRIVATE_KEY => $tenant->passport_private_key, self::PUBLIC_KEY => $tenant->passport_public_key] as $file => $contents) {
            $path = $directory . '/' . $file;
            if (!$this->files->exists($path) || $this->files->get($path) !== $contents) {
                $this->files->put($path, $contents);
            }
        }
        PassportKeyPermissions::apply($directory);

        return true;
    }
}
