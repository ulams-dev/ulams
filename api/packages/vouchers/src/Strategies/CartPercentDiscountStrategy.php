<?php

namespace Ulams\Vouchers\Strategies;

use Ulams\Vouchers\Models\Cart;
use Ulams\Vouchers\Models\CartItem;
use Ulams\Vouchers\Services\Contracts\CouponServiceContract;
use Ulams\Vouchers\Strategies\Abstracts\DiscountStrategy;
use Ulams\Vouchers\Strategies\Contracts\DiscountStrategyContract;

class CartPercentDiscountStrategy extends DiscountStrategy implements DiscountStrategyContract
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
        // @phpstan-ignore-next-line
        return (int) round($this->coupon->amount * $cartItem->buyable->getBuyablePrice() / 100, 0);
    }
}
