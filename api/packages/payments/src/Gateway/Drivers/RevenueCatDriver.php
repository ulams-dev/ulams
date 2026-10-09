<?php

namespace Ulams\Payments\Gateway\Drivers;

use Ulams\Payments\Exceptions\ActionNotSupported;
use Ulams\Payments\Gateway\Contracts\ReceiptVerifier;
use Ulams\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use Ulams\Payments\Gateway\Responses\CallbackRefundResponse;
use Ulams\Payments\Gateway\Responses\CallbackResponse;
use Ulams\Payments\Gateway\Responses\FailedGatewayResponse;
use Ulams\Payments\Gateway\Responses\NoneGatewayResponse;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\Request;
use Omnipay\Common\Message\ResponseInterface;

/**
 * In-app purchases made through RevenueCat.
 *
 * The client cannot be trusted to report a purchase, so a payment is only accepted after a configured
 * ReceiptVerifier confirmed it server-side. Without a verifier every purchase is refused.
 */
class RevenueCatDriver extends AbstractDriver implements GatewayDriverContract
{
    public function purchase(Payment $payment, array $parameters = []): ResponseInterface
    {
        $verifier = $this->receiptVerifier();

        if (is_null($verifier)) {
            return new FailedGatewayResponse(
                __('RevenueCat purchases require server-side receipt verification, which is not configured'),
                'receipt_verification_not_configured'
            );
        }

        if (!$verifier->verify($payment, $parameters)) {
            return new FailedGatewayResponse(__('The in-app purchase could not be verified'), 'receipt_not_verified');
        }

        return new NoneGatewayResponse();
    }

    public function callback(Request $request, array $parameters = []): CallbackResponse
    {
        return CallbackResponse::rejected('RevenueCat payments are not settled through callbacks');
    }

    public static function requiredParameters(): array
    {
        return [];
    }

    public function callbackRefund(Request $request, array $parameters = []): CallbackRefundResponse
    {
        return new CallbackRefundResponse(false, null, null, null, 'Refunds are not supported by the RevenueCat gateway');
    }

    public function refund(Request $request, Payment $payment, array $parameters = []): ResponseInterface
    {
        return throw new ActionNotSupported();
    }

    private function receiptVerifier(): ?ReceiptVerifier
    {
        $class = $this->config->getRevenueCatReceiptVerifier();

        if (is_null($class)) {
            return null;
        }

        $verifier = app($class);

        return $verifier instanceof ReceiptVerifier ? $verifier : null;
    }
}
