<?php

namespace EscolaLms\Vouchers\Strategies;

use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Models\CartItem;
use EscolaLms\Vouchers\Services\Contracts\CouponServiceContract;
use EscolaLms\Vouchers\Strategies\Abstracts\DiscountStrategy;
use EscolaLms\Vouchers\Strategies\Contracts\DiscountStrategyContract;

class CartFixedDiscountStrategy extends DiscountStrategy implements DiscountStrategyContract
{
    public function calculateAdditionalDiscount(Cart $cart): int
    {
        return 0;
    }

    public function calculateDiscountForItem(Cart $cart, CartItem $cartItem): int
    {
        if (app(CouponServiceContract::class)->cartItemIsExcludedFromCoupon($this->coupon, $cartItem)) {
            return 0;
        }

        $totalAmount = 0;
        $maxAmount = $this->coupon->amount;

        foreach ($cart->items as $item) {
            if (!app(CouponServiceContract::class)->cartItemIsExcludedFromCoupon($this->coupon, $item)) {
                $tax = (1 + $item->tax_rate / 100);
                $totalAmount += $item->basePrice * $tax;
            }
        }

        $maxAmount = min($totalAmount, $maxAmount);

        if ($totalAmount > 0) {
            $tax = (1 + $cartItem->tax_rate / 100);
            $itemValue = $cartItem->basePrice * $tax;
            $discount = round($itemValue / $totalAmount * $maxAmount, 0);
            return (int) round($discount / $tax, 0);
        }

        return 0;
    }
}
