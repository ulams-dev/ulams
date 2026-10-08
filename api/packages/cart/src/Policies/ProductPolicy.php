<?php

namespace Ulams\Cart\Policies;

use Ulams\Cart\Enums\CartPermissionsEnum;
use Illuminate\Auth\Access\HandlesAuthorization;
use Ulams\Core\Models\User;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Services\Contracts\ProductServiceContract;

class ProductPolicy
{
    use HandlesAuthorization;

    private ProductServiceContract $productService;

    public function __construct(ProductServiceContract $productService)
    {
        $this->productService = $productService;
    }

    public function viewAny(User $user)
    {
        return $user->can(CartPermissionsEnum::LIST_ALL_PRODUCTS);
    }

    public function viewMy(User $user)
    {
        return $user->can(CartPermissionsEnum::LIST_PURCHASABLE_PRODUCTS);
    }

    public function viewPurchasable(?User $user = null)
    {
        return true;
    }

    public function view(User $user, Product $product)
    {
        return $user->can(CartPermissionsEnum::LIST_ALL_PRODUCTS)
            || ($user->can(CartPermissionsEnum::LIST_PURCHASABLE_PRODUCTS) && $this->productService->productIsPurchasableOrOwnedByUser($product, $user));
    }

    public function buy(User $user, Product $product)
    {
        return $user->can(CartPermissionsEnum::BUY_PRODUCTS);
    }

    public function create(User $user)
    {
        return $user->can(CartPermissionsEnum::MANAGE_PRODUCTS);
    }

    public function update(User $user, Product $product)
    {
        return $user->can(CartPermissionsEnum::MANAGE_PRODUCTS);
    }

    public function delete(User $user, Product $product)
    {
        return $user->can(CartPermissionsEnum::MANAGE_PRODUCTS);
    }

    public function attach(User $user, Product $product)
    {
        return $user->can(CartPermissionsEnum::MANAGE_PRODUCTS);
    }

    public function detach(User $user, Product $product)
    {
        return $user->can(CartPermissionsEnum::MANAGE_PRODUCTS);
    }

    public function manuallyTrigger(User $user, Product $product)
    {
        return $user->can(CartPermissionsEnum::MANAGE_PRODUCTS);
    }

    public function attachToProduct(User $user)
    {
        return $user->can(CartPermissionsEnum::BUY_PRODUCTS);
    }

    public function cancelProductRecursive(User $user)
    {
        return $user->can(CartPermissionsEnum::BUY_PRODUCTS);
    }
}
