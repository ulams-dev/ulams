<?php

namespace Ulams\Commerce\Contracts;

use Illuminate\Http\Request;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\OrderSnapshot;
use Ulams\Commerce\Support\ProductSpec;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;

/**
 * What the LMS needs from a commerce backend (ADR 0049). The LMS owns entitlements: a provider never
 * decides who has access, it only mirrors a sellable as a product, starts a checkout and reports
 * verified order events. The Wellms cart is the default adapter; the Sylius adapter is `commerce-sylius` (6.4).
 *
 * Interface v2 (ADR 0130): `syncProduct` takes a {@see ProductSpec}, `fetchOrder` is part of the core, and optional
 * capabilities ({@see ReconcilesOrders}, {@see InspectsCatalogue}, {@see ListsOrders}) keep
 * it small for a provider that has none of them.
 */
interface CommerceProvider
{
    /** Stable key, used in `commerce.provider` and stored with every product link ("wellms", "sylius"). */
    public function key(): string;

    /**
     * Creates or updates the product that sells `$ref` (idempotent: the same sellable always maps to
     * the same product). `$spec->active = false` keeps it hidden from buyers until the course is published and
     * disables it (never deletes it) when the course is unpublished, because orders reference it.
     */
    public function syncProduct(SellableRef $ref, ProductSpec $spec): ProductRef;

    public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef;

    /**
     * Turns a provider webhook into a verified, idempotent order event, or null when this provider
     * does not use webhooks (the Wellms cart grants access itself when a payment succeeds).
     */
    public function handleOrderEvent(Request $request): ?OrderEvent;

    /**
     * The order as the provider reports it now (not as a webhook claims it); the truth for every grant and revoke.
     * Throws when the order is unknown, belongs to another channel or the provider is unreachable (the job retries).
     */
    public function fetchOrder(string $orderId): OrderSnapshot;
}
