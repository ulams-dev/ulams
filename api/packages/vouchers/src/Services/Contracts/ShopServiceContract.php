<?php

namespace Ulams\Vouchers\Services\Contracts;

use Ulams\Cart\Models\Cart as BaseCart;
use Ulams\Cart\Services\Contracts\ShopServiceContract as BaseShopServiceContract;
use Ulams\Core\Models\User;
use Ulams\Vouchers\Models\Cart;
use Ulams\Vouchers\Services\CartManager;
use Illuminate\Http\Resources\Json\JsonResource;

interface ShopServiceContract extends BaseShopServiceContract
{
    public function cartForUser(User $user): Cart;
    public function cartManagerForCart(BaseCart $cart): CartManager;
    public function cartAsJsonResource(BaseCart $cart, ?int $taxRate = null): JsonResource;
}
