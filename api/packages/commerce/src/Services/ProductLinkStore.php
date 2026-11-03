<?php

namespace Ulams\Commerce\Services;

use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\ProductSpec;
use Ulams\Commerce\Support\SellableRef;

/** Writes `commerce_product_links`, the map from a sellable to the provider's product. Adapters call it after a sync. */
class ProductLinkStore
{
    public function find(SellableRef $ref, string $provider): ?ProductLink
    {
        return ProductLink::query()->where(['sellable_type' => $ref->type, 'sellable_id' => $ref->id, 'provider' => $provider])->first();
    }

    public function record(SellableRef $ref, ProductRef $product, ProductSpec $spec, ?string $variantCode = null, ?string $channel = null): ProductLink
    {
        return ProductLink::query()->updateOrCreate(
            ['sellable_type' => $ref->type, 'sellable_id' => $ref->id, 'provider' => $product->provider],
            [
                'external_id' => $product->externalId, 'variant_code' => $variantCode, 'channel_code' => $channel,
                'amount_minor' => $spec->price->amountMinor, 'currency' => $spec->price->currency, 'active' => $spec->active,
                'synced_at' => now(), 'sync_error' => null, 'payload_hash' => $spec->hash(),
            ],
        );
    }

    /** A failed push is kept on the link (when there is one) so the admin and the drift check can show why. */
    public function recordError(SellableRef $ref, string $provider, string $error): void
    {
        $this->find($ref, $provider)?->forceFill(['sync_error' => mb_substr($error, 0, 1000)])->save();
    }
}
