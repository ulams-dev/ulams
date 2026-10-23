<?php

namespace Ulams\Commerce\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Models\SellablePrice;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Commerce\Support\SellableRef;

/**
 * Catalogue sync from the command line (ADR 0130). Without options it pushes every sellable that has a price and is
 * not yet in step (also how a tenant that just switched provider fills the new catalogue); `--check` lists drift and
 * exits non-zero when there is any, `--repair` also fixes it. The scheduler runs `--check --repair` every night and
 * stores the report.
 */
class SyncCommand extends Command
{
    protected $signature = 'ulams:commerce:sync
        {--provider= : Provider key, default commerce.provider}
        {--sellable= : Only this sellable, e.g. course:12, bundle:3, access_pass:7}
        {--check : Compare the provider\'s products with the LMS and report drift}
        {--repair : With --check, push every drifted sellable}
        {--force : Send even products that look unchanged}';

    protected $description = 'Push priced sellables to the commerce provider and check the catalogue for drift';

    public function handle(CatalogueSync $sync, CommerceManager $commerce): int
    {
        $key = (string) ($this->option('provider') ?: config('commerce.provider'));
        if (!$commerce->has($key)) {
            $this->error("Unknown commerce provider \"{$key}\".");

            return self::FAILURE;
        }

        if ($this->option('check') || $this->option('repair')) {
            $result = $sync->checkAndRepair((bool) $this->option('repair'), $key);
            $this->table(['Sellable', 'Drift', 'Expected', 'Actual'], array_map(fn ($d) => [$d->sellable->key(), $d->kind, $d->expected, $d->actual], $result['drift']));
            $this->line(count($result['drift']) . ' drifted, ' . $result['repaired'] . ' repaired.');

            return ($result['drift'] === [] || $result['repaired'] >= count($result['drift'])) ? self::SUCCESS : self::FAILURE;
        }

        try {
            $refs = $this->option('sellable') ? [$this->parse((string) $this->option('sellable'))] : SellablePrice::query()->orderBy('id')->get()->map->ref()->all();
        } catch (InvalidArgumentException) {
            $this->error('"' . $this->option('sellable') . '" is not a sellable; use type:id such as course:12.');

            return self::INVALID;
        }
        $pushed = $failed = 0;
        foreach ($refs as $ref) {
            try {
                $sync->push($ref, $key, (bool) $this->option('force')) !== null && $pushed++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("{$ref->key()}: {$e->getMessage()}");
            }
        }
        $this->info("Synced {$pushed} sellable(s), {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function parse(string $value): SellableRef
    {
        [$type, $id] = array_pad(explode(':', $value, 2), 2, '0');
        return new SellableRef($type, (int) $id);
    }
}
