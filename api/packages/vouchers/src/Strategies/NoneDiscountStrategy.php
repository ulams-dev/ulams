<?php

namespace Ulams\Vouchers\Strategies;

use Ulams\Vouchers\Models\Cart;
use Ulams\Vouchers\Models\CartItem;
use Ulams\Vouchers\Strategies\Contracts\DiscountStrategyContract;

class NoneDiscountStrategy implements DiscountStrategyContract
{
    public function calculateAdditionalDiscount(Cart $cart): int
    {
        return 0;
    }

    public function calculateDiscountForItem(Cart $cart, CartItem $cartItem): int
    {
        return 0;
    }
}
