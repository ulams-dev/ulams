<?php

namespace Ulams\Payments\Gateway\Drivers;

use Ulams\Payments\Exceptions\ActionNotSupported;
use Ulams\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use Ulams\Payments\Gateway\Responses\CallbackRefundResponse;
use Ulams\Payments\Gateway\Responses\CallbackResponse;
use Ulams\Payments\Gateway\Responses\FailedGatewayResponse;
use Ulams\Payments\Gateway\Responses\NoneGatewayResponse;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\Request;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Settles zero-amount payments. Any payment with a positive amount is refused.
 */
class FreeDriver extends AbstractDriver implements GatewayDriverContract
{
    public function purchase(Payment $payment, array $parameters = []): ResponseInterface
    {
        if (!self::isFree($payment)) {
            return new FailedGatewayResponse(__('The free gateway only accepts zero-amount payments'));
        }

        return new NoneGatewayResponse();
    }

    public function callback(Request $request, array $parameters = []): CallbackResponse
    {
        $payment = $parameters['payment'] ?? null;

        if (!$payment instanceof Payment || !self::isFree($payment)) {
            return CallbackResponse::rejected('The free gateway only accepts zero-amount payments');
        }

        return CallbackResponse::success();
    }

    public static function requiredParameters(): array
    {
        return [];
    }

    public function callbackRefund(Request $request, array $parameters = []): CallbackRefundResponse
    {
        return new CallbackRefundResponse(false, null, null, null, 'Refunds are not supported by the free gateway');
    }

    public function refund(Request $request, Payment $payment, array $parameters = []): ResponseInterface
    {
        return throw new ActionNotSupported();
    }

    private static function isFree(Payment $payment): bool
    {
        return (int) $payment->amount === 0;
    }
}
