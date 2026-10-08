<?php

namespace Ulams\Cart\Listeners;

use Ulams\Cart\Models\Order;
use Ulams\Cart\Services\Contracts\OrderServiceContract;
use Ulams\Payments\Events\PaymentSuccess;

class PaymentSuccessListener
{
    protected OrderServiceContract $orderService;

    public function __construct(OrderServiceContract $orderService)
    {
        $this->orderService = $orderService;
    }

    public function handle(PaymentSuccess $event)
    {
        $payment = $event->getPayment();
        if ($payment->payable instanceof Order) {
            $this->orderService->setPaid($payment->payable->refresh());
        }
    }
}
