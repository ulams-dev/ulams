<?php

namespace Ulams\Payments\Gateway\Drivers;

use Ulams\Payments\Dtos\PaymentDto;
use Ulams\Payments\Entities\PaymentsConfig;
use Ulams\Payments\Exceptions\ActionNotSupported;
use Ulams\Payments\Exceptions\CardDeclined;
use Ulams\Payments\Exceptions\ExpiredCard;
use Ulams\Payments\Exceptions\IncorrectCvc;
use Ulams\Payments\Exceptions\ProcessingError;
use Ulams\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use Ulams\Payments\Gateway\Responses\CallbackRefundResponse;
use Ulams\Payments\Gateway\Responses\CallbackResponse;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\Request;
use Omnipay\Common\GatewayInterface;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Omnipay;
use Omnipay\Stripe\PaymentIntentsGateway;

class StripeDriver extends AbstractDriver implements GatewayDriverContract
{
    /** @var PaymentIntentsGateway $gateway */
    private GatewayInterface $gateway;

    public function __construct(PaymentsConfig $config)
    {
        $this->config = $config;

        $gateway = Omnipay::create('Stripe\PaymentIntents');
        assert($gateway instanceof PaymentIntentsGateway);
        $this->gateway = $gateway;
        $this->gateway->setApiKey($this->config->getStripeSecretKey());
    }

    public function purchase(Payment $payment, array $parameters = []): ResponseInterface
    {
        $this->throwExceptionIfMissingParameters($parameters);
        return $this->gateway->purchase([
            'amount' => number_format($payment->amount / 100, 2, '.', ''),
            'currency' => (string) ($payment->currency ?? $this->config->getDefaultCurrency()),
            'description' => $payment->description,
            'paymentMethod' => $parameters['payment_method'],
            'returnUrl' => $parameters['return_url'],
            'confirm' => true,
            'metadata' => [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->getKey(),
            ],

        ])->send();
    }

    public function callback(Request $request, array $parameters = []): CallbackResponse
    {
        return new CallbackResponse();
    }

    public static function requiredParameters(): array
    {
        return [
            'return_url',
            'payment_method',
        ];
    }

    public function throwExceptionForResponse(ResponseInterface $response): void
    {
        switch ($response->getCode()) {
            case 'card_declined':
                throw new CardDeclined($response->getMessage());
            case 'expired_card':
                throw new ExpiredCard($response->getMessage());
            case 'incorrect_cvc':
                throw new IncorrectCvc($response->getMessage());
            case 'processing_error':
                throw new ProcessingError($response->getMessage());
            default:
                parent::throwExceptionForResponse($response);
        };
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
