<?php

namespace EscolaLms\Vouchers\Strategies;

use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Models\CartItem;
use EscolaLms\Vouchers\Strategies\Contracts\DiscountStrategyContract;

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
