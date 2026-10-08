<?php

namespace Ulams\Cart\Services\Contracts;

use Carbon\Carbon;
use Ulams\Cart\Dtos\ClientDetailsDto;
use Ulams\Cart\Models\Cart;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Services\CartManager;
use Ulams\Core\Models\User;
use Ulams\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

interface ShopServiceContract
{
    public function cartForUser(User $user): Cart;

    public function cartManagerForCart(Cart $cart): CartManager;

    public function cartAsJsonResource(Cart $cart, ?int $taxRate = null): JsonResource;

    public function addProductToCart(Cart $cart, Product $buyable, int $quantity = 1): void;
    public function removeProductFromCart(Cart $cart, Product $buyable, int $quantity = 1): void;
    public function updateProductQuantity(Cart $cart, Product $buyable, int $quantity): array;

    public function addMissingProductsToCart(Cart $cart, array $products): void;

    public function purchaseCart(Cart $cart, ?ClientDetailsDto $clientDetails = null, array $parameters = []): Payment;
    public function purchaseProduct(Product $product, User $user, ?ClientDetailsDto $clientDetails = null, array $parameters = []): Payment;

    public function getAbandonedCarts(Carbon $from, Carbon $to): Collection;
}
