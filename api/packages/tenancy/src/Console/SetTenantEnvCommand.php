<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Support\TenantContext;
use Ulams\Tenancy\Support\TenantNaming;

class SetTenantEnvCommand extends Command
{
    protected $signature = 'ulams:tenant:set-env {slug}
        {--set=* : KEY=value override for this tenant (inheritable settings only, e.g. AI_DRIVER=fake)}
        {--unset=* : KEY to drop, so the tenant inherits the platform value again}';

    protected $description = 'Override or reset inheritable platform settings (AI key, driver, models) for one tenant';

    public function handle(TenantProvisioner $provisioner): int
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

        $allowed = TenantNaming::inheritableKeys();
        $overrides = (array) ($tenant->env_overrides ?? []);
        foreach ((array) $this->option('set') as $pair) {
            [$key, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $key = strtoupper(trim($key));
            if (!in_array($key, $allowed, true) || $value === '') {
                $this->error('Use --set=KEY=value with one of: ' . implode(', ', $allowed));

                return self::FAILURE;
            }
            $overrides[$key] = $value;
        }
        foreach ((array) $this->option('unset') as $key) {
            unset($overrides[strtoupper(trim((string) $key))]);
        }

        $tenant->env_overrides = $overrides ?: null;
        $tenant->save();
        // only key names are printed, never values
        $this->line("  <info>{$tenant->slug}</info> overrides: " . ($overrides ? implode(', ', array_keys($overrides)) : 'none (inherits the platform)'));

        $provisioner->syncRuntime($tenant);

        return self::SUCCESS;
    }
}
