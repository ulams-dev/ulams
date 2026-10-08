<?php

namespace EscolaLms\Payments\Gateway\Drivers;

use EscolaLms\Payments\Entities\PaymentsConfig;
use EscolaLms\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use EscolaLms\Payments\Gateway\Responses\CallbackRefundResponse;
use EscolaLms\Payments\Gateway\Responses\CallbackResponse;
use EscolaLms\Payments\Gateway\Responses\Przelewy24GatewayResponse;
use EscolaLms\Payments\Gateway\Responses\Przelewy24RefundResponse;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Http\Request;
use Omnipay\Common\Message\ResponseInterface;
use Przelewy24\Api\Requests\Items\RefundItem;
use Przelewy24\Api\Responses\Transaction\RegisterTransactionResponse;
use Przelewy24\Enums\Currency as Przelewy24Currency;
use Przelewy24\Enums\TransactionChannel;
use Przelewy24\Exceptions\Przelewy24Exception;
use Przelewy24\Przelewy24;

class Przelewy24Driver extends AbstractDriver implements GatewayDriverContract
{
    private Przelewy24 $gateway;

    public function __construct(PaymentsConfig $config)
    {
        $this->config = $config;

        $this->gateway = new Przelewy24(
            $this->config->getPrzelewy24MerchantId(),
            $this->config->getPrzelewy24ApiKey(),
            $this->config->getPrzelewy24Crc(),
            $this->config->getPrzelewy24Live(),
            $this->config->getPrzelewy24PosId(),
        );
    }

    public function purchase(Payment $payment, array $parameters = []): ResponseInterface
    {
        $this->throwExceptionIfMissingParameters($parameters);

        try {
            if ($this->hasRecursiveSubscription($parameters)) {
                $parameters += ['channel' => TransactionChannel::CARDS_ONLY->value];
            }

            $response = $this->transaction($payment, $parameters);
        } catch (Przelewy24Exception $exception) {
            return Przelewy24GatewayResponse::fromApiResponseException($exception);
        }

        return Przelewy24GatewayResponse::fromRegisterTransactionResponse($response);
    }

    public function callback(Request $request, array $parameters = []): CallbackResponse
    {
        $callbackNotification = $this->gateway->handleWebhook($request->input());

        try {
            $response = $this->gateway->transactions()->verify(
                sessionId: $callbackNotification->sessionId(),
                orderId: $callbackNotification->orderId(),
                amount: $callbackNotification->amount(),
                currency: $callbackNotification->currency(),
            );

            return new CallbackResponse(true, $callbackNotification->orderId());
        } catch (Przelewy24Exception $exception) {
            return new CallbackResponse(false, $callbackNotification->orderId(), $exception->getMessage());
        }
    }

    private function transaction(Payment $payment, array $parameters = []): RegisterTransactionResponse
    {
        if (isset($parameters['gateway_order_id'])) {
            $cardInfoResponse = $this->gateway->cards()->cardInfo($parameters['gateway_order_id']);
        }

        $transaction = $this->gateway->transactions()->register(
            sessionId: ($payment->order_id ? $payment->order_id . '_' : '') . $payment->getKey() . $payment->created_at->timestamp,
            amount: $payment->amount,
            description: !empty($payment->description) ? $payment->description : 'Payment',
            email: $parameters['email'],
            urlReturn: $parameters['return_url'] ?? url('/'),
            currency: Przelewy24Currency::tryFrom($payment->currency) ?? Przelewy24Currency::PLN,
            urlStatus: route('payments-gateway-callback', ['payment' => $payment->getKey()]),
            channel: !empty($parameters['channel']) ? TransactionChannel::CARDS_ONLY->value : TransactionChannel::ALL_24_7->value,
            methodRefId: isset($cardInfoResponse) ? $cardInfoResponse->refId() : null
        );

        if (isset($parameters['gateway_order_id'])) {
            $this->gateway->cards()->cardCharge($transaction->token());
        }

        return $transaction;
    }

    public function refund(Request $request, Payment $payment, array $parameters = []): ResponseInterface
    {
        try {
            $res = $this->gateway->transactions()->refund(
                requestId: $parameters['gateway_request_id'],
                refunds: [
                    new RefundItem(
                        $payment->gateway_order_id,
                        ($payment->order_id ? $payment->order_id . '_' : '') . $payment->getKey() . $payment->created_at->timestamp,
                        $payment->amount
                    )
                ],
                refundsUuid: $parameters['gateway_refunds_uuid'],
                urlStatus: route('payments-gateway-refund-callback', ['payment' => $payment->getKey()]),
            );

            return Przelewy24RefundResponse::from($parameters['gateway_request_id'], $parameters['gateway_refunds_uuid']);
        } catch (Przelewy24Exception $exception) {
            return Przelewy24RefundResponse::fromApiResponseException($exception);
        }
    }

    public function callbackRefund(Request $request, array $parameters = []): CallbackRefundResponse
    {
        try {
            $refundNotification = $this->gateway->handleRefundWebhook($request->input());
            return new CallbackRefundResponse(true, $refundNotification->orderId(), $refundNotification->requestId(), $refundNotification->refundsUuid());
        } catch (Przelewy24Exception $exception) {
            return new CallbackRefundResponse(false, null, null, null, $exception->getMessage());
        }
    }

    public static function requiredParameters(): array
    {
        return [
            'return_url',
            'email'
        ];
    }

    private function hasRecursiveSubscription(array $parameters = []): bool
    {
        if (!$parameters) {
            return false;
        }

        return isset($parameters['type'])
            && in_array($parameters['type'], ['subscription', 'subscription-all-in'])
            && isset($parameters['recursive'])
            && $parameters['recursive'] === true;
    }

    public function ableToRenew(): bool
    {
        return true;
    }
}
