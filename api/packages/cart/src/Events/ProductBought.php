<?php

namespace Ulams\Cart\Events;

use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\Product;
use Ulams\Core\Models\User;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductBought
{
    use Dispatchable, SerializesModels;

    private Product $product;
    private Order $order;
    private User $user;

    public function __construct(Product $product, Order $order, ?User $user = null)
    {
        $this->product = $product;
        $this->order = $order;
        $this->user = $user ?? $order->user;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
