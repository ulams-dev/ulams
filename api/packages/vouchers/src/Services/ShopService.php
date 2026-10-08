<?php

namespace EscolaLms\Vouchers\Services;

use EscolaLms\Cart\Models\Cart as BaseCart;
use EscolaLms\Cart\Services\Contracts\ProductServiceContract;
use EscolaLms\Cart\Services\ShopService as CartShopService;
use EscolaLms\Core\Models\User;
use EscolaLms\Vouchers\Http\Resources\CartResource;
use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Services\Contracts\OrderServiceContract;
use EscolaLms\Vouchers\Services\Contracts\ShopServiceContract;
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
