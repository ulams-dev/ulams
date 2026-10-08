<?php

namespace EscolaLms\Vouchers\Services\Contracts;

use EscolaLms\Cart\Dtos\ClientDetailsDto;
use EscolaLms\Cart\Models\Cart as BaseCart;
use EscolaLms\Cart\Services\CartManager as BaseCartManager;
use EscolaLms\Cart\Services\Contracts\OrderServiceContract as BaseOrderServiceContract;
use EscolaLms\Vouchers\Models\Order;

interface OrderServiceContract extends BaseOrderServiceContract
{
    public function createOrderFromCart(BaseCart $cart, ?ClientDetailsDto $clientDetailsDto = null): Order;
    public function createOrderFromCartManager(BaseCartManager $cartManager, ?ClientDetailsDto $clientDetailsDto = null): Order;
}
