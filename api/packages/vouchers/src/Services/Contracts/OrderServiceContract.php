<?php

namespace Ulams\Vouchers\Services\Contracts;

use Ulams\Cart\Dtos\ClientDetailsDto;
use Ulams\Cart\Models\Cart as BaseCart;
use Ulams\Cart\Services\CartManager as BaseCartManager;
use Ulams\Cart\Services\Contracts\OrderServiceContract as BaseOrderServiceContract;
use Ulams\Vouchers\Models\Order;

interface OrderServiceContract extends BaseOrderServiceContract
{
    public function createOrderFromCart(BaseCart $cart, ?ClientDetailsDto $clientDetailsDto = null): Order;
    public function createOrderFromCartManager(BaseCartManager $cartManager, ?ClientDetailsDto $clientDetailsDto = null): Order;
}
