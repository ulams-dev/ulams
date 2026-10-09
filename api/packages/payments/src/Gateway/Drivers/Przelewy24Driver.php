<?php

namespace Ulams\Payments\Gateway\Drivers;

use Ulams\Payments\Entities\PaymentsConfig;
use Ulams\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use Ulams\Payments\Gateway\Responses\CallbackRefundResponse;
use Ulams\Payments\Gateway\Responses\CallbackResponse;
use Ulams\Payments\Gateway\Responses\Przelewy24GatewayResponse;
use Ulams\Payments\Gateway\Responses\Przelewy24RefundResponse;
use Ulams\Payments\Models\Payment;
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

    /**
     * The status URL is public. A notification is accepted only when its signature is valid for the configured CRC,
     * it names this merchant and POS, it refers to this payment's session and matches its amount and currency, and
     * the transaction is then confirmed with Przelewy24's verify call.
     */
    public function callback(Request $request, array $parameters = []): CallbackResponse
    {
        $payment = $parameters['payment'] ?? null;

        if (!$payment instanceof Payment) {
            return CallbackResponse::rejected('Missing payment');
        }

        try {
            $notification = $this->gateway->handleWebhook($request->input());

            if (!$notification->isSignatureValid()) {
                return CallbackResponse::rejected('Invalid Przelewy24 signature');
            }

            if (
                (string) $notification->merchantId() !== (string) $this->config->getPrzelewy24MerchantId()
                || (string) $notification->posId() !== (string) $this->config->getPrzelewy24PosId()
            ) {
                return CallbackResponse::rejected('Przelewy24 notification is for another merchant');
            }

            if ($notification->sessionId() !== $this->sessionId($payment)) {
                return CallbackResponse::rejected('Przelewy24 notification does not belong to this payment');
            }

            if ((int) $notification->amount() !== (int) $payment->amount || $notification->currency() !== $this->currency($payment)) {
                return CallbackResponse::rejected('Przelewy24 notification amount or currency does not match the payment');
            }
        } catch (\Throwable $exception) {
            return CallbackResponse::rejected('Malformed Przelewy24 notification');
        }

        try {
            $this->gateway->transactions()->verify(
                sessionId: $notification->sessionId(),
                orderId: $notification->orderId(),
                amount: $notification->amount(),
                currency: $notification->currency(),
            );

            return CallbackResponse::success((string) $notification->orderId());
        } catch (Przelewy24Exception $exception) {
            return CallbackResponse::failed($exception->getMessage(), (string) $notification->orderId());
        }
    }

    private function sessionId(Payment $payment): string
    {
        return ($payment->order_id ? $payment->order_id . '_' : '') . $payment->getKey() . $payment->created_at->timestamp;
    }

    private function currency(Payment $payment): Przelewy24Currency
    {
        return Przelewy24Currency::tryFrom((string) $payment->currency) ?? Przelewy24Currency::PLN;
    }

    private function transaction(Payment $payment, array $parameters = []): RegisterTransactionResponse
    {
        if (isset($parameters['gateway_order_id'])) {
            $cardInfoResponse = $this->gateway->cards()->cardInfo($parameters['gateway_order_id']);
        }

        $transaction = $this->gateway->transactions()->register(
            sessionId: $this->sessionId($payment),
            amount: $payment->amount,
            description: !empty($payment->description) ? $payment->description : 'Payment',
            email: $parameters['email'],
            urlReturn: $parameters['return_url'] ?? url('/'),
            currency: $this->currency($payment),
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
                        $this->sessionId($payment),
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

            if (!$refundNotification->isSignatureValid()) {
                return new CallbackRefundResponse(false, null, null, null, 'Invalid Przelewy24 refund signature');
            }

            return new CallbackRefundResponse(true, $refundNotification->orderId(), $refundNotification->requestId(), $refundNotification->refundsUuid());
        } catch (\Throwable $exception) {
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
