<?php

namespace EscolaLms\Vouchers\Services\Contracts;

use EscolaLms\Cart\Models\Cart as BaseCart;
use EscolaLms\Cart\Services\Contracts\ShopServiceContract as BaseShopServiceContract;
use EscolaLms\Core\Models\User;
use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Services\CartManager;
use Illuminate\Http\Resources\Json\JsonResource;

interface ShopServiceContract extends BaseShopServiceContract
{
    public function cartForUser(User $user): Cart;
    public function cartManagerForCart(BaseCart $cart): CartManager;
    public function cartAsJsonResource(BaseCart $cart, ?int $taxRate = null): JsonResource;
}
