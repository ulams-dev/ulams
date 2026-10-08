<?php

namespace Ulams\Vouchers\Strategies\Contracts;

use Ulams\Vouchers\Models\Cart;
use Ulams\Vouchers\Models\CartItem;

interface DiscountStrategyContract
{
    public function calculateAdditionalDiscount(Cart $cart): int;
    public function calculateDiscountForItem(Cart $cart, CartItem $cartItem): int;
}
