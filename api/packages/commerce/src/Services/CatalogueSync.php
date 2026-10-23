<?php

namespace Ulams\Commerce\Services;

use InvalidArgumentException;
use Throwable;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Contracts\InspectsCatalogue;
use Ulams\Commerce\Models\AccessPass;
use Ulams\Commerce\Models\BundleCourse;
use Ulams\Commerce\Models\DriftReport;
use Ulams\Commerce\Models\SellablePrice;
use Ulams\Commerce\Support\DriftItem;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\ProductSpec;
use Ulams\Commerce\Support\SellableRef;

/**
 * Keeps the provider's catalogue in step with what this tenant wants to sell (ADR 0130). The LMS holds the intent
 * (`commerce_sellable_prices`: a price and "for sale"); the provider only mirrors it as a product, which is enabled
 * while the sellable is for sale and every course in it is published, and disabled (never deleted, orders reference
 * it) otherwise.
 *
 * - `setPrice()` records the intent and pushes it;
 * - `push()` builds the `ProductSpec` and calls the provider; an unchanged spec is not sent again;
 * - `check()` reads the products back and lists drift (a product deleted, re-priced or enabled by hand);
 * - `repair()` pushes whatever `check()` found.
 */
class CatalogueSync
{
    public function __construct(
        private readonly CommerceManager $commerce,
        private readonly SellableCatalogue $catalogue,
        private readonly ProductLinkStore $links,
    ) {
    }

    /** Records what the tenant sells `$ref` for. Does not call the provider: dispatch `SyncSellableJob` or call `push()`. */
    public function setPrice(SellableRef $ref, Price $price, bool $forSale = true): SellablePrice
    {
        $this->catalogue->describe($ref); // refuses a sellable that does not exist

        return SellablePrice::query()->updateOrCreate(
            ['sellable_type' => $ref->type, 'sellable_id' => $ref->id],
            ['amount_minor' => $price->amountMinor, 'currency' => $price->currency, 'for_sale' => $forSale],
        );
    }

    /** The spec the provider should hold for `$ref`, or null when the sellable has no price yet or no longer exists. */
    public function spec(SellableRef $ref): ?ProductSpec
    {
        $price = SellablePrice::query()->where(['sellable_type' => $ref->type, 'sellable_id' => $ref->id])->first();
        if ($price === null) {
            return null;
        }
        try {
            $info = $this->catalogue->describe($ref);
        } catch (InvalidArgumentException) {
            return null;
        }

        return new ProductSpec($info->name, $price->price(), $price->for_sale && $info->published, $info->description, $info->accessDays);
    }

    /**
     * Sends the current spec to the provider. Returns null when there is nothing to send (no price) or the product
     * already matches. `$force` sends it anyway (repair). Errors are kept on the link and rethrown for the job to retry.
     */
    public function push(SellableRef $ref, ?string $providerKey = null, bool $force = false): ?ProductRef
    {
        $provider = $this->commerce->provider($providerKey);
        $spec = $this->spec($ref);
        if ($spec === null) {
            return null;
        }
        $link = $this->links->find($ref, $provider->key());
        if (!$force && $link !== null && $link->sync_error === null && $link->payload_hash === $spec->hash()) {
            return null;
        }
        try {
            $product = $provider->syncProduct($ref, $spec);
            // adapters record their own link (variant, channel); this only covers one that does not
            if ($this->links->find($ref, $provider->key()) === null) {
                $this->links->record($ref, $product, $spec);
            }

            return $product;
        } catch (Throwable $e) {
            $this->links->recordError($ref, $provider->key(), $e->getMessage());

            throw $e;
        }
    }

    /**
     * The sellables whose product depends on a course: the course itself, the bundles that contain it, and the passes
     * on either. A course published or unpublished must reach all of them.
     *
     * @return list<SellableRef>
     */
    public function affectedByCourse(int $courseId): array
    {
        $refs = [SellableRef::course($courseId)];
        $bundleIds = BundleCourse::query()->where('course_id', $courseId)->pluck('bundle_id')->map(fn ($id) => (int) $id)->all();
        foreach ($bundleIds as $id) {
            $refs[] = SellableRef::bundle($id);
        }
        $passes = AccessPass::query()->where(fn ($q) => $q->where('subject_type', SellableRef::COURSE)->where('subject_id', $courseId)
            ->orWhere(fn ($q) => $q->where('subject_type', SellableRef::BUNDLE)->whereIn('subject_id', $bundleIds)))->pluck('id');
        foreach ($passes as $id) {
            $refs[] = SellableRef::accessPass((int) $id);
        }

        return array_values(array_filter($refs, fn (SellableRef $r) => SellablePrice::query()->where(['sellable_type' => $r->type, 'sellable_id' => $r->id])->exists()));
    }

    /** @return list<DriftItem> */
    public function check(?string $providerKey = null): array
    {
        $provider = $this->commerce->provider($providerKey);
        $drift = [];
        foreach (SellablePrice::query()->orderBy('id')->get() as $price) {
            $ref = $price->ref();
            $spec = $this->spec($ref);
            if ($spec === null) {
                continue; // the sellable is gone from this tenant: nothing to compare
            }
            $link = $this->links->find($ref, $provider->key());
            if ($link === null) {
                $drift[] = new DriftItem($ref, DriftItem::MISSING, $spec->active ? 'enabled' : 'disabled', null);
                continue;
            }
            if ($link->sync_error !== null) {
                $drift[] = new DriftItem($ref, DriftItem::ERROR, null, mb_substr($link->sync_error, 0, 200));
                continue;
            }
            if (!$provider instanceof InspectsCatalogue) {
                if ($link->payload_hash !== $spec->hash()) {
                    $drift[] = new DriftItem($ref, DriftItem::STALE);
                }
                continue;
            }
            try {
                $state = $provider->inspectProduct(new ProductRef($provider->key(), $link->external_id));
            } catch (Throwable $e) {
                $drift[] = new DriftItem($ref, DriftItem::UNREACHABLE, null, mb_substr($e->getMessage(), 0, 200));
                continue;
            }
            foreach ($this->differences($ref, $spec, $state) as $item) {
                $drift[] = $item;
            }
        }

        return $drift;
    }

    /**
     * Runs `check()`, optionally pushes every drifted sellable, and stores the report. A failed push is counted as not
     * repaired and left in the report.
     *
     * @return array{drift:list<DriftItem>,repaired:int}
     */
    public function checkAndRepair(bool $repair, ?string $providerKey = null): array
    {
        $provider = $this->commerce->provider($providerKey);
        $drift = $this->check($providerKey);
        $repaired = 0;
        if ($repair) {
            foreach (collect($drift)->unique(fn (DriftItem $d) => $d->sellable->key()) as $item) {
                try {
                    $this->push($item->sellable, $provider->key(), force: true);
                    $repaired += collect($drift)->filter(fn (DriftItem $d) => $d->sellable->key() === $item->sellable->key())->count();
                } catch (Throwable) {
                    // kept on the link as sync_error; the next check reports it
                }
            }
        }
        DriftReport::query()->create([
            'kind' => DriftReport::CATALOGUE, 'provider' => $provider->key(), 'drift' => count($drift), 'repaired' => $repaired,
            'details' => array_map(fn (DriftItem $d) => $d->toArray(), array_slice($drift, 0, 100)),
        ]);

        return ['drift' => $drift, 'repaired' => $repaired];
    }

    /** @return list<DriftItem> */
    private function differences(SellableRef $ref, ProductSpec $spec, ?\Ulams\Commerce\Support\ProductState $state): array
    {
        if ($state === null) {
            return [new DriftItem($ref, DriftItem::MISSING, $spec->active ? 'enabled' : 'disabled', null)];
        }
        $items = [];
        if ($state->priceMinor !== $spec->price->amountMinor || $state->currency !== $spec->price->currency) {
            $items[] = new DriftItem($ref, DriftItem::PRICE, "{$spec->price->amountMinor} {$spec->price->currency}", "{$state->priceMinor} {$state->currency}");
        }
        if ($state->enabled !== $spec->active) {
            $items[] = new DriftItem($ref, DriftItem::ENABLED, $spec->active ? 'enabled' : 'disabled', $state->enabled ? 'enabled' : 'disabled');
        }
        if ($state->name !== $spec->name) {
            $items[] = new DriftItem($ref, DriftItem::NAME, $spec->name, $state->name);
        }

        return $items;
    }
}
