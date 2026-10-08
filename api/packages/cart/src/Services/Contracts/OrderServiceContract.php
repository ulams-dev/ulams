<?php

namespace Ulams\Cart\Services\Contracts;

use Ulams\Cart\Dtos\ClientDetailsDto;
use Ulams\Cart\Dtos\OrdersSearchDto;
use Ulams\Cart\Models\Cart;
use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\User;
use Ulams\Cart\Services\CartManager;
use Ulams\Core\Dtos\OrderDto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderServiceContract
{
    public function searchAndPaginateOrders(OrdersSearchDto $searchDto, ?OrderDto $sortDto): LengthAwarePaginator;

    public function find(int $id): Model;

    public function createOrderFromCart(Cart $cart, ?ClientDetailsDto $clientDetailsDto = null): Order;
    public function createOrderFromCartManager(CartManager $cart, ?ClientDetailsDto $clientDetailsDto = null): Order;
    public function createOrderFromProduct(Product $product, int $userId, ?ClientDetailsDto $clientDetailsDto = null): Order;

    public function setPaid(Order $order): void;
    public function setCancelled(Order $order): void;
    public function setOrderStatus(Order $order, int $status): void;

    public function processOrderItems(Order $order): void;
    public function searchOrders(OrdersSearchDto $searchDto, ?OrderDto $sortDto): Builder;
}
