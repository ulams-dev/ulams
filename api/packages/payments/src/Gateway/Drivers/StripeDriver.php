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
use Illuminate\Support\Facades\Http;
use Throwable;
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

    /**
     * The callback route is public, so the request itself proves nothing. The PaymentIntent is trusted only when it
     * comes from a webhook signed with the configured endpoint secret, or when it is fetched from the Stripe API
     * with the secret key. It must belong to this payment (metadata.payment_id), match its amount and currency,
     * and have the status "succeeded".
     */
    public function callback(Request $request, array $parameters = []): CallbackResponse
    {
        $payment = $parameters['payment'] ?? null;

        if (!$payment instanceof Payment) {
            return CallbackResponse::rejected('Missing payment');
        }

        if ($request->hasHeader('Stripe-Signature')) {
            $event = $this->verifiedEvent($request);

            if (is_null($event)) {
                return CallbackResponse::rejected('Invalid Stripe webhook signature');
            }

            $intent = $event['data']['object'] ?? null;

            if (!is_array($intent) || ($intent['object'] ?? null) !== 'payment_intent') {
                return CallbackResponse::rejected('Unsupported Stripe event');
            }

            return $this->evaluatePaymentIntent($payment, $intent);
        }

        $intentId = $request->input('payment_intent');

        if (is_string($intentId) && preg_match('/^pi_[A-Za-z0-9_]+$/', $intentId)) {
            $intent = $this->fetchPaymentIntent($intentId);

            if (is_null($intent)) {
                return CallbackResponse::rejected('PaymentIntent could not be fetched from Stripe');
            }

            return $this->evaluatePaymentIntent($payment, $intent);
        }

        return CallbackResponse::rejected('Missing Stripe signature');
    }

    /**
     * Returns the decoded event when the request carries a valid Stripe-Signature for the configured webhook secret.
     */
    public function verifiedEvent(Request $request): ?array
    {
        $secret = $this->config->getStripeWebhookSecret();

        if (is_null($secret)) {
            return null;
        }

        $payload = (string) $request->getContent();

        if (!self::isValidSignature($payload, $request->header('Stripe-Signature'), $secret, $this->config->getStripeWebhookTolerance())) {
            return null;
        }

        $event = json_decode($payload, true);

        return is_array($event) ? $event : null;
    }

    /**
     * Stripe webhook signature scheme v1: HMAC-SHA256 of "{timestamp}.{payload}" with the endpoint secret.
     */
    public static function isValidSignature(string $payload, ?string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        if (empty($header) || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't' && is_numeric($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && is_string($value) && $value !== '') {
                $signatures[] = $value;
            }
        }

        if (is_null($timestamp) || empty($signatures)) {
            return false;
        }

        if ($tolerance > 0 && abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function fetchPaymentIntent(string $intentId): ?array
    {
        try {
            $response = Http::withToken((string) $this->config->getStripeSecretKey())
                ->acceptJson()
                ->timeout(15)
                ->get($this->config->getStripeApiBase() . '/v1/payment_intents/' . $intentId);
        } catch (Throwable $exception) {
            return null;
        }

        if (!$response->successful() || !is_array($response->json())) {
            return null;
        }

        return $response->json();
    }

    private function evaluatePaymentIntent(Payment $payment, array $intent): CallbackResponse
    {
        $intentId = isset($intent['id']) ? (string) $intent['id'] : null;

        if ((string) ($intent['metadata']['payment_id'] ?? '') !== (string) $payment->getKey()) {
            return CallbackResponse::rejected('PaymentIntent does not belong to this payment');
        }

        if ((int) ($intent['amount'] ?? -1) !== (int) $payment->amount) {
            return CallbackResponse::rejected('PaymentIntent amount does not match the payment');
        }

        $currency = (string) ($payment->currency ?? $this->config->getDefaultCurrency());

        if (strtolower((string) ($intent['currency'] ?? '')) !== strtolower($currency)) {
            return CallbackResponse::rejected('PaymentIntent currency does not match the payment');
        }

        $status = $intent['status'] ?? null;

        if ($status === 'succeeded') {
            return CallbackResponse::success($intentId);
        }

        if ($status === 'canceled') {
            return CallbackResponse::failed('Stripe payment was canceled', $intentId);
        }

        if ($status === 'requires_payment_method' && !empty($intent['last_payment_error'])) {
            return CallbackResponse::failed(
                (string) ($intent['last_payment_error']['message'] ?? 'Stripe payment failed'),
                $intentId
            );
        }

        return CallbackResponse::rejected('Stripe payment is not settled (status: ' . (is_string($status) ? $status : 'unknown') . ')');
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
        return new CallbackRefundResponse(false, null, null, null, 'Refunds are not supported by the Stripe gateway');
    }

    public function refund(Request $request, Payment $payment, array $parameters = []): ResponseInterface
    {
        return throw new ActionNotSupported();
    }
}
