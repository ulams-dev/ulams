<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Support\TenantContext;

class SetTenantEnvCommand extends Command
{
    protected $signature = 'ulams:tenant:set-env {slug}
        {--set=* : KEY=value override for this tenant (inheritable settings only, e.g. AI_DRIVER=fake)}
        {--unset=* : KEY to drop, so the tenant inherits the platform value again}';

    protected $description = 'Override or reset inheritable platform settings (AI key, driver, models) for one tenant';

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

        $set = [];
        foreach ((array) $this->option('set') as $pair) {
            [$key, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $set[strtoupper(trim($key))] = $value;
        }
        try {
            $lifecycle->applyEnv($tenant, $set, (array) $this->option('unset'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $overrides = (array) ($tenant->env_overrides ?? []);
        // only key names are printed, never values
        $this->line("  <info>{$tenant->slug}</info> overrides: " . ($overrides ? implode(', ', array_keys($overrides)) : 'none (inherits the platform)'));

        return self::SUCCESS;
    }
}
