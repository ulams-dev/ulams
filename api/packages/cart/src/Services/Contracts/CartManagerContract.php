<?php

namespace Ulams\Cart\Services\Contracts;

use Ulams\Cart\Models\CartItem;
use Ulams\Cart\Models\Contracts\Base\Buyable;
use Ulams\Cart\Models\Product;
use Treestoneit\ShoppingCart\CartContract;

interface CartManagerContract extends CartContract
{
    public function subtotalInt(): int;
    public function taxInt(?int $rate = null): int;
    public function total(): int;
    public function totalWithTax(?int $rate = null): int;
    public function hasBuyable(Buyable $buyable): bool;
    public function findBuyable(Buyable $buyable): ?CartItem;
    public function hasProduct(Product $product): bool;
    public function findProduct(Product $buyable): ?CartItem;
}
