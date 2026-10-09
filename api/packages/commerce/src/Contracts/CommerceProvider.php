<?php

namespace Ulams\Commerce\Contracts;

use Illuminate\Http\Request;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;

/**
 * What the LMS needs from a commerce backend (ADR 0049). The LMS owns entitlements: a provider never
 * decides who has access, it only mirrors a sellable as a product, starts a checkout and reports
 * verified order events. The Wellms cart is the default adapter; a Sylius adapter follows in 6.4.
 */
interface CommerceProvider
{
    /** Stable key, used in `commerce.provider` and stored with every product link ("wellms", "sylius"). */
    public function key(): string;

    /**
     * Creates or updates the product that sells `$ref` (idempotent: the same sellable always maps to
     * the same product). `$active = false` keeps it hidden from buyers until the course is published.
     */
    public function syncProduct(SellableRef $ref, Price $price, bool $active): ProductRef;

    public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef;

    /**
     * Turns a provider webhook into a verified, idempotent order event, or null when this provider
     * does not use webhooks (the Wellms cart grants access itself when a payment succeeds).
     */
    public function handleOrderEvent(Request $request): ?OrderEvent;
}
