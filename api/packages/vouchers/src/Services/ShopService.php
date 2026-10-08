<?php

namespace Ulams\Vouchers\Services;

use Ulams\Cart\Models\Cart as BaseCart;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Services\ShopService as CartShopService;
use Ulams\Core\Models\User;
use Ulams\Vouchers\Http\Resources\CartResource;
use Ulams\Vouchers\Models\Cart;
use Ulams\Vouchers\Services\Contracts\OrderServiceContract;
use Ulams\Vouchers\Services\Contracts\ShopServiceContract;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopService extends CartShopService implements ShopServiceContract
{
    public function __construct(
        OrderServiceContract $orderService,
        ProductServiceContract $productService
    ) {
        parent::__construct($orderService, $productService);
    }

    public function cartForUser(User $user): Cart
    {
        return Cart::where('user_id', $user->getAuthIdentifier())->latest()->firstOrCreate([
            'user_id' => $user->getAuthIdentifier(),
        ]);
    }

    public function cartManagerForCart(BaseCart $cart): CartManager
    {
        return new CartManager($cart);
    }

    public function cartAsJsonResource(BaseCart $cart, ?int $taxRate = null): JsonResource
    {
        return CartResource::make($cart, $taxRate);
    }
}
