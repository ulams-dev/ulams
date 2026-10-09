<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantUpgradeStep;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Support\TenantContext;
use Ulams\Tenancy\Upgrade\UpgradeContext;
use Ulams\Tenancy\Upgrade\UpgradeStep;
use Ulams\Tenancy\Upgrade\UpgradeSteps;

class UpgradeCommand extends Command
{
    protected $signature = 'ulams:upgrade
        {--tenant=* : Only these tenant slugs (the platform is then left out)}
        {--platform-only : Only the platform}
        {--force-step=* : Run this one-off step again even if it already ran}
        {--dry-run : List what would run; change and record nothing}';

    protected $description = 'Bring the platform and every tenant to the current release: migrations, permissions, keys and one-off data steps';

    public function handle(TenantCommandRunnerContract $runner): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run the upgrade on the platform, without --domain.');

            return self::FAILURE;
        }

        $known = array_map(fn (UpgradeStep $step) => $step->name, UpgradeSteps::all());
        foreach ((array) $this->option('force-step') as $name) {
            if (!in_array($name, $known, true)) {
                $this->error("Unknown step '{$name}'. Steps: " . implode(', ', $known));

                return self::FAILURE;
            }
        }

        $targets = $this->targets();
        if ($targets === null) {
            return self::FAILURE;
        }

        $results = [];
        foreach ($targets as [$target, $tenant]) {
            $results[] = $this->upgrade($target, $tenant, $runner);
        }

        $this->newLine();
        $this->table(['target', 'ran', 'skipped', 'result'], array_map(fn (array $r) => [$r['target'], $r['ran'], $r['skipped'], $r['status']], $results));

        $failed = array_filter($results, fn (array $r) => $r['status'] === 'failed');
        if ($failed) {
            $this->error(count($failed) . ' of ' . count($results) . ' targets failed: ' . implode(', ', array_column($failed, 'target')));

            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run: nothing was changed.' : 'Upgrade finished.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: Tenant|null}>|null
     */
    private function targets(): ?array
    {
        $slugs = array_filter((array) $this->option('tenant'));
        $hasTenants = Schema::hasTable((new Tenant())->getTable());
        $targets = [];

        if (!$slugs) {
            $targets[] = [TenantUpgradeStep::PLATFORM, null];
        }
        if ($this->option('platform-only')) {
            return $targets;
        }
        if (!$hasTenants) {
            return $targets;
        }

        $query = Tenant::query()->orderBy('slug');
        if ($slugs) {
            $query->whereIn('slug', $slugs);
        }
        $tenants = $query->get();
        foreach (array_diff($slugs, $tenants->pluck('slug')->all()) as $missing) {
            $this->error("Tenant '{$missing}' does not exist.");

            return null;
        }
        foreach ($tenants as $tenant) {
            $targets[] = [$tenant->slug, $tenant];
        }

        return $targets;
    }

    /**
     * @return array{target: string, ran: int, skipped: int, status: string}
     */
    private function upgrade(string $target, ?Tenant $tenant, TenantCommandRunnerContract $runner): array
    {
        $this->line("<options=bold>{$target}</>");
        $result = ['target' => $target, 'ran' => 0, 'skipped' => 0, 'status' => 'ok'];

        if ($tenant && ($tenant->status !== Tenant::STATUS_ACTIVE || !$tenant->hasCompleted('env'))) {
            $this->warn("  skipped: status is '{$tenant->status}' (finish provisioning with ulams:tenant:create first)");
            $result['status'] = 'skipped';

            return $result;
        }

        $context = new UpgradeContext($target, $tenant?->api_host, $tenant
            ? fn (string $command, array $arguments) => $runner->run($tenant->api_host, [$command, ...self::tokens($arguments)])
            : fn (string $command, array $arguments) => self::runOnPlatform($command, $arguments));
        $done = TenantUpgradeStep::query()->where('target', $target)->pluck('step')->all();
        $forced = (array) $this->option('force-step');
        $available = array_keys(Artisan::all());

        foreach (UpgradeSteps::forScope($tenant === null) as $step) {
            if ($step->requiresCommand && !in_array($step->requiresCommand, $available, true)) {
                $this->line("  <comment>skip</comment> {$step->name} (command {$step->requiresCommand} is not available in this release)");
                $result['skipped']++;
                continue;
            }
            if ($step->once && in_array($step->name, $done, true) && !in_array($step->name, $forced, true)) {
                $this->line("  skip {$step->name} (already ran)");
                $result['skipped']++;
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("  would run {$step->name}" . ($step->once ? '' : ' (every upgrade)'));
                $result['ran']++;
                continue;
            }

            $this->line("  run {$step->name}" . ($step->since ? " (since {$step->since})" : ''));
            try {
                $output = trim((string) ($step->callback)($context));
            } catch (Throwable $exception) {
                $this->error("  {$step->name} failed: " . trim($exception->getMessage()));
                $result['status'] = 'failed';

                return $result;
            }
            if ($output !== '') {
                $this->line('    ' . str_replace("\n", "\n    ", $output));
            }
            if ($step->once) {
                TenantUpgradeStep::query()->updateOrCreate(
                    ['target' => $target, 'step' => $step->name],
                    ['since' => $step->since, 'ran_at' => now()]
                );
            }
            $result['ran']++;
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private static function runOnPlatform(string $command, array $arguments): string
    {
        $exitCode = Artisan::call($command, $arguments);
        $output = Artisan::output();
        if ($exitCode !== 0) {
            throw new RuntimeException("`artisan {$command}` failed (exit {$exitCode}):\n{$output}");
        }

        return $output;
    }

    /**
     * Artisan::call() style arguments as command line tokens.
     *
     * @param array<int|string, mixed> $arguments
     * @return list<string>
     */
    public static function tokens(array $arguments): array
    {
        $tokens = [];
        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                $tokens[] = (string) $value;
            } elseif ($value === true) {
                $tokens[] = $key;
            } elseif ($value !== false && $value !== null) {
                foreach ((array) $value as $item) {
                    $tokens[] = "{$key}={$item}";
                }
            }
        }

        return $tokens;
    }
}
