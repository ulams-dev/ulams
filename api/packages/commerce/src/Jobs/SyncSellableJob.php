<?php

namespace Ulams\Commerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Commerce\Support\UnsupportedSellable;

/**
 * Pushes one sellable to the commerce provider (catalogue sync). Retries with backoff while the provider is down;
 * an unchanged product is not sent again, so a repeat is harmless. A provider that cannot sell the sellable at all
 * (the Wellms cart cannot sell a bundle) is not retried: the reason stays on the product link.
 */
class SyncSellableJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 6;

    public function __construct(public readonly string $type, public readonly int $id, public readonly ?string $provider = null)
    {
    }

    /** @return int[] */
    public function backoff(): array
    {
        return [10, 30, 120, 600, 1800];
    }

    public function handle(CatalogueSync $sync): void
    {
        try {
            $sync->push(new SellableRef($this->type, $this->id), $this->provider);
        } catch (UnsupportedSellable) {
            return;
        }
    }
}
