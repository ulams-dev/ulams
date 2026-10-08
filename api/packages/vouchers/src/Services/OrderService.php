<?php

namespace Ulams\Vouchers\Services;

use Ulams\Cart\Dtos\ClientDetailsDto;
use Ulams\Cart\Models\Cart as BaseCart;
use Ulams\Cart\Models\Order as BaseOrder;
use Ulams\Cart\Services\CartManager as BaseCartManager;
use Ulams\Cart\Services\OrderService as BaseOrderService;
use Ulams\Vouchers\Models\Order;
use Ulams\Vouchers\Services\CartManager;
use Ulams\Vouchers\Services\Contracts\OrderServiceContract;
use Illuminate\Database\Eloquent\Model;

class OrderService extends BaseOrderService implements OrderServiceContract
{
    /** @return Order */
    // @phpstan-ignore-next-line
    public function find($id): Model
    {
        /** @var Order $order */
        $order = Order::findOrFail($id);
        return $order;
    }

    public function createOrderFromCart(BaseCart $cart, ?ClientDetailsDto $clientDetailsDto = null): Order
    {
        return $this->createOrderFromCartManager(new CartManager($cart), $clientDetailsDto);
    }

    public function createOrderFromCartManager(BaseCartManager $cartManager, ?ClientDetailsDto $clientDetailsDto = null): Order
    {
        if (!$cartManager instanceof CartManager) {
            // @phpstan-ignore-next-line
            $cartManager = new CartManager($cartManager->getModel());
        }
        $order = parent::createOrderFromCartManager($cartManager, $clientDetailsDto);
        /** @var Order $order */
        $order = $order instanceof Order ? $order : Order::find($order->getKey());
        $order->coupon_id = optional($cartManager->getCoupon())->getKey();
        $order->discount = $cartManager->additionalDiscount();
        $order->save();
        return $order;
    }
}
