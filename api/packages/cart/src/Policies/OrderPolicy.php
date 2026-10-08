<?php

namespace Ulams\Cart\Policies;

use Ulams\Cart\Enums\CartPermissionsEnum;
use Ulams\Cart\Models\Order;
use Ulams\Core\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->can(CartPermissionsEnum::LIST_ALL_ORDERS);
    }

    public function viewOwn(User $user)
    {
        return true;
    }

    public function view(User $user, Order $order)
    {
        return $user->can(CartPermissionsEnum::LIST_ALL_ORDERS)
            || $user->getKey() === $order->user_id;
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(?User $user, Order $order)
    {
        return false;
    }

    public function delete(?User $user, Order $order)
    {
        return false;
    }

    public function export(?User $user): bool
    {
        return $user && $user->can(CartPermissionsEnum::ORDERS_EXPORT);
    }
}
