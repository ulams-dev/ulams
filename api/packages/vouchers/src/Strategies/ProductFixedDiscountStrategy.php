<?php

namespace EscolaLms\Vouchers\Strategies;

use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Models\CartItem;
use EscolaLms\Vouchers\Services\Contracts\CouponServiceContract;
use EscolaLms\Vouchers\Strategies\Abstracts\DiscountStrategy;
use EscolaLms\Vouchers\Strategies\Contracts\DiscountStrategyContract;

class ProductFixedDiscountStrategy extends DiscountStrategy implements DiscountStrategyContract
{
    public function calculateAdditionalDiscount(Cart $cart): int
    {
        return 0;
    }

    public function calculateDiscountForItem(Cart $cart, CartItem $cartItem): int
    {
        if (!app(CouponServiceContract::class)->cartItemIsIncludedInCoupon($this->coupon, $cartItem)) {
            return 0;
        }
        $tax = (1 + $cartItem->tax_rate / 100);
        $itemValue = $cartItem->basePrice * $tax;
        $discount = $itemValue < $this->coupon->amount ? $itemValue : $this->coupon->amount;
        if ($discount === $itemValue) {
            return (int) $cartItem->basePrice;
        }
        return (int) round($discount / $tax, 0);
    }
}
