<?php

namespace EscolaLms\Vouchers\Strategies\Contracts;

use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Models\CartItem;

interface DiscountStrategyContract
{
    public function calculateAdditionalDiscount(Cart $cart): int;
    public function calculateDiscountForItem(Cart $cart, CartItem $cartItem): int;
}
