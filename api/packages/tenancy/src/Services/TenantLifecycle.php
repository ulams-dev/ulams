<?php

namespace Ulams\Tenancy\Services;

use Closure;
use InvalidArgumentException;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantUpgradeStep;
use Ulams\Tenancy\Support\RedisKeyPurger;
use Ulams\Tenancy\Support\TenantNaming;

/**
 * What the artisan commands and the platform API both do to a tenant: create or resume its
 * registry row, change its inheritable settings, remove it with everything it owns. One place,
 * so `ulams:tenant:*` and `/api/platform/tenants` cannot drift apart (ADR 0078).
 */
class TenantLifecycle
{
    /** Deletion steps in the order the operation reports them. */
    public const DELETE_STEPS = ['database', 'bucket', 'env', 'redis'];

    public function __construct(private TenantProvisioner $provisioner, private RedisKeyPurger $redis)
    {
    }

    /**
     * Creates the registry row (or finds the existing one) and applies the given changes,
     * forgetting the steps that must run again.
     *
     * @param array{name?: ?string, theme?: ?string, accent?: ?string, demo?: ?string} $options `demo` is `on` or `off`
     * @param list<string> $redo steps to run again
     *
     * @throws InvalidArgumentException on an invalid slug, option or step name
     */
    public function prepare(string $slug, array $options = [], array $redo = []): Tenant
    {
        TenantNaming::assertValidSlug($slug);

        foreach (['theme' => '/^[A-Za-z0-9_-]{1,40}$/', 'accent' => '/^#[0-9A-Fa-f]{6}$/', 'demo' => '/^(on|off)$/'] as $option => $pattern) {
            $value = $options[$option] ?? null;
            if ($value !== null && !preg_match($pattern, (string) $value)) {
                throw new InvalidArgumentException("Invalid --{$option} value '{$value}'.");
            }
        }

        $tenant = Tenant::query()->firstWhere('slug', $slug)
            ?? new Tenant(TenantNaming::newTenantAttributes($slug));

        $changes = array_filter([
            'name' => $options['name'] ?? null,
            'theme' => $options['theme'] ?? null,
            'accent' => $options['accent'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        $tenant->fill($changes);
        if (($options['demo'] ?? null) !== null) {
            $tenant->demo = $options['demo'] === 'on';
        }

        if ($tenant->exists && $tenant->isDirty(['name', 'theme', 'accent'])) {
            // Display name and theme live in the env file and the settings table.
            $tenant->forget('env', 'demo');
        } elseif ($tenant->exists && $tenant->isDirty('demo')) {
            // DEMO_MODE lives in the env file only.
            $tenant->forget('env');
        }
        $unknown = array_diff($redo, TenantProvisioner::STEPS);
        if ($unknown) {
            throw new InvalidArgumentException('Unknown step(s) in --redo: ' . implode(', ', $unknown));
        }
        $tenant->forget(...$redo);
        $tenant->save();

        return $tenant;
    }

    /**
     * Sets and drops tenant-level overrides of the inheritable platform settings, then rewrites
     * the tenant's env file.
     *
     * @param array<string, string> $set
     * @param list<string> $unset
     *
     * @throws InvalidArgumentException on a key outside the allow-list or an empty value
     */
    public function applyEnv(Tenant $tenant, array $set, array $unset): Tenant
    {
        $allowed = TenantNaming::inheritableKeys();
        $overrides = (array) ($tenant->env_overrides ?? []);
        foreach ($set as $key => $value) {
            $key = strtoupper(trim((string) $key));
            if (!in_array($key, $allowed, true) || !is_string($value) || $value === '') {
                throw new InvalidArgumentException('Use --set=KEY=value with one of: ' . implode(', ', $allowed));
            }
            $overrides[$key] = $value;
        }
        foreach ($unset as $key) {
            unset($overrides[strtoupper(trim((string) $key))]);
        }

        $tenant->env_overrides = $overrides ?: null;
        $tenant->save();
        $this->provisioner->syncRuntime($tenant);

        return $tenant;
    }

    /**
     * Drops the database, role, bucket objects, env file, storage and Redis keys, then the row.
     *
     * @param Closure(string $step): void|null $report receives each step as it starts
     */
    public function delete(Tenant $tenant, ?Closure $report = null): void
    {
        $report ??= fn () => null;
        $this->provisioner->deprovision($tenant, $report);
        $report('redis');
        $this->redis->purge($tenant->redis_prefix);
        TenantUpgradeStep::query()->where('target', $tenant->slug)->delete();
        $tenant->delete();
    }
}
