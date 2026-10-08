<?php

namespace Ulams\Payments\Gateway\Drivers;

use Ulams\Payments\Exceptions\ActionNotSupported;
use Ulams\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use Ulams\Payments\Gateway\Responses\CallbackRefundResponse;
use Ulams\Payments\Gateway\Responses\CallbackResponse;
use Ulams\Payments\Gateway\Responses\NoneGatewayResponse;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\Request;
use Omnipay\Common\Message\ResponseInterface;

class RevenueCatDriver extends AbstractDriver implements GatewayDriverContract
{
    public function purchase(Payment $payment, array $parameters = []): ResponseInterface
    {
        return new NoneGatewayResponse();
    }

    public function callback(Request $request, array $parameters = []): CallbackResponse
    {
        return new CallbackResponse();
    }

    public static function requiredParameters(): array
    {
        return [];
    }

    public function callbackRefund(Request $request, array $parameters = []): CallbackRefundResponse
    {
        return new CallbackRefundResponse();
    }

    public function refund(Request $request, Payment $payment, array $parameters = []): ResponseInterface
    {
        return throw new ActionNotSupported();
    }
}
