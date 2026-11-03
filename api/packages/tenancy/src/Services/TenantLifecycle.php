<?php

namespace Ulams\Tenancy\Services;

use Closure;
use InvalidArgumentException;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantTemplate;
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

    public function __construct(private TenantProvisioner $provisioner, private RedisKeyPurger $redis, private ?TenantDerivations $derivations = null)
    {
        $this->derivations ??= new TenantDerivations();
    }

    /**
     * Creates the registry row (or finds the existing one) and applies the given changes,
     * forgetting the steps that must run again.
     *
     * @param array{name?: ?string, theme?: ?string, accent?: ?string, demo?: ?string, db_password?: ?string, template?: ?string} $options `demo` is `on` or `off`; `db_password` is for an operator-created database and applies until the `database` step is done; `template` is the slug of a tenant template whose theme, accent, demo flag and settings are the starting point of a tenant that does not exist yet (the other options win over it)
     * @param list<string> $redo steps to run again
     *
     * @throws InvalidArgumentException on an invalid slug, option or step name
     */
    public function prepare(string $slug, array $options = [], array $redo = []): Tenant
    {
        TenantNaming::assertValidSlug($slug);

        $template = null;
        $isNew = !Tenant::query()->where('slug', $slug)->exists();
        if (($options['template'] ?? null) !== null && $options['template'] !== '') {
            $template = TenantTemplate::query()->firstWhere('slug', (string) $options['template'])
                ?? throw new InvalidArgumentException("Unknown tenant template '{$options['template']}'.");
            // a template is the starting point of a tenant that does not exist yet; an existing tenant keeps what it has
            if ($isNew) {
                $fromTemplate = array_filter(['name' => $template->name, 'theme' => $template->theme, 'accent' => $template->accent, 'demo' => $template->demo ? 'on' : null], fn ($value) => $value !== null && $value !== '');
                $options = array_replace($fromTemplate, array_filter($options, fn ($value) => $value !== null && $value !== ''));
            }
        }

        foreach (['theme' => '/^[A-Za-z0-9_-]{1,40}$/', 'accent' => '/^#[0-9A-Fa-f]{6}$/', 'demo' => '/^(on|off)$/'] as $option => $pattern) {
            $value = $options[$option] ?? null;
            if ($value !== null && !preg_match($pattern, (string) $value)) {
                throw new InvalidArgumentException("Invalid --{$option} value '{$value}'.");
            }
        }

        $tenant = Tenant::query()->firstWhere('slug', $slug)
            ?? new Tenant(TenantNaming::newTenantAttributes($slug));

        $dbPassword = (string) ($options['db_password'] ?? '');
        if ($dbPassword !== '' && strlen($dbPassword) < 16) {
            throw new InvalidArgumentException('--db-password must be at least 16 characters.');
        }
        if ($dbPassword !== '' && !$tenant->hasCompleted('database')) {
            $tenant->db_password = $dbPassword;
        }

        $changes = array_filter([
            'name' => $options['name'] ?? null,
            'theme' => $options['theme'] ?? null,
            'accent' => $options['accent'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        $tenant->fill($changes);
        if ($template !== null && $isNew && $template->env_overrides) {
            $tenant->env_overrides = $template->env_overrides;
        }
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
    public function delete(Tenant $tenant, ?Closure $report = null, bool $withDerived = false): void
    {
        $report ??= fn () => null;
        // the tenants created from this one go first, or the delete is refused: nothing is left orphaned
        foreach ($this->derivations->activeOf($tenant->slug) as $child) {
            if (!$withDerived) {
                throw new InvalidArgumentException("'{$tenant->slug}' has tenants created from it ({$child->tenant_slug}, ...): delete them first, or delete with the tenants created from it.");
            }
            $childTenant = Tenant::query()->firstWhere('slug', $child->tenant_slug);
            if ($childTenant !== null) {
                $this->delete($childTenant, $report);
            } else {
                $this->derivations->markDeleted($child->tenant_slug);
            }
        }
        $this->provisioner->deprovision($tenant, $report);
        $report('redis');
        $this->redis->purge($tenant->redis_prefix);
        TenantUpgradeStep::query()->where('target', $tenant->slug)->delete();
        $tenant->delete();
        $this->derivations->markDeleted($tenant->slug);
    }
}
