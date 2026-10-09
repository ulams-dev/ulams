<?php

namespace Ulams\Payments\Tests\Api;

use Ulams\Payments\Enums\Currency;
use Ulams\Payments\Enums\PaymentStatus;
use Ulams\Payments\Events\PaymentSuccess;
use Ulams\Payments\Exceptions\PaymentException;
use Ulams\Payments\Facades\PaymentGateway;
use Ulams\Payments\Facades\Payments;
use Ulams\Payments\Gateway\Contracts\ReceiptVerifier;
use Ulams\Payments\Gateway\Drivers\StripeDriver;
use Ulams\Payments\Models\Payment;
use Ulams\Payments\Tests\Mocks\Payable;
use Ulams\Payments\Tests\TestCase;
use Ulams\Payments\Tests\Traits\CreatesBillable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Przelewy24\Przelewy24;

/**
 * Gateway callbacks are public endpoints: only notifications verified with the provider may mark a payment as paid.
 */
class PaymentCallbackSecurityTest extends TestCase
{
    use CreatesBillable;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ulams_payments.drivers.stripe.enabled', true);
        Config::set('ulams_payments.drivers.stripe.secret_key', 'sk_test_key');
        Config::set('ulams_payments.drivers.stripe.publishable_key', 'pk_test_key');
        Config::set('ulams_payments.drivers.stripe.webhook_secret', self::WEBHOOK_SECRET);
        Config::set('ulams_payments.drivers.przelewy24.enabled', true);
        Config::set('ulams_payments.drivers.przelewy24.merchant_id', '11111');
        Config::set('ulams_payments.drivers.przelewy24.pos_id', '11111');
        Config::set('ulams_payments.drivers.przelewy24.api_key', 'p24_api_key');
        Config::set('ulams_payments.drivers.przelewy24.crc', 'p24_crc');
        $this->refreshGatewayManager();
    }

    private function refreshGatewayManager(): void
    {
        $this->app->forgetInstance('payment-gateway');
        Facade::clearResolvedInstance('payment-gateway');
    }

    private function createPendingPayment(string $driver, int $amount = 1000, string $currency = Currency::USD): Payment
    {
        $payable = new Payable($amount, Currency::fromValue($currency), 'Course', 'order-1');
        $payable->setUser($this->createBillableStudent());

        $payment = $payable->process()->getPayment();
        $payment->update(['driver' => $driver, 'status' => PaymentStatus::REQUIRES_REDIRECT]);

        return $payment->refresh();
    }

    private function paymentIntent(Payment $payment, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'pi_test_123',
            'object' => 'payment_intent',
            'amount' => $payment->amount,
            'currency' => 'usd',
            'status' => 'succeeded',
            'metadata' => ['payment_id' => (string) $payment->getKey(), 'order_id' => $payment->order_id],
        ], $overrides);
    }

    private function stripeEvent(array $intent, string $type = 'payment_intent.succeeded'): string
    {
        return json_encode([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $intent],
        ]);
    }

    private function signature(string $payload, string $secret = self::WEBHOOK_SECRET, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    private function postSigned(string $uri, string $payload, ?string $signature)
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (!is_null($signature)) {
            $server['HTTP_STRIPE_SIGNATURE'] = $signature;
        }

        return $this->call('POST', $uri, [], [], [], $server, $payload);
    }

    private function assertNotPaid(Payment $payment): void
    {
        $this->assertNotEquals(PaymentStatus::PAID, $payment->refresh()->status->value);
        Event::assertNotDispatched(PaymentSuccess::class);
    }

    public function testCallbackResponseIsNotSuccessfulByDefault(): void
    {
        $this->assertFalse((new \Ulams\Payments\Gateway\Responses\CallbackResponse())->getSuccess());
        $this->assertFalse((new \Ulams\Payments\Gateway\Responses\CallbackRefundResponse())->getSuccess());
    }

    public function testStripeCallbackWithoutSignatureDoesNotMarkPaymentPaid(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('stripe');

        $this->postJson('api/payments-gateways/callback/' . $payment->getKey())->assertStatus(400);

        $this->assertNotPaid($payment);
        $this->assertEquals(PaymentStatus::REQUIRES_REDIRECT, $payment->status->value);
    }

    public function testStripeCallbackWithWrongSignatureDoesNotMarkPaymentPaid(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('stripe');
        $payload = $this->stripeEvent($this->paymentIntent($payment));

        $this->postSigned('api/payments-gateways/callback/' . $payment->getKey(), $payload, $this->signature($payload, 'whsec_other'))
            ->assertStatus(400);
        $this->postSigned('api/payments-gateways/callback/' . $payment->getKey(), $payload, 't=' . time() . ',v1=deadbeef')
            ->assertStatus(400);
        // A valid signature that is too old is a replay.
        $this->postSigned('api/payments-gateways/callback/' . $payment->getKey(), $payload, $this->signature($payload, self::WEBHOOK_SECRET, time() - 3600))
            ->assertStatus(400);

        $this->assertNotPaid($payment);
    }

    public function testStripeCallbackIsRejectedWhenWebhookSecretIsNotConfigured(): void
    {
        Config::set('ulams_payments.drivers.stripe.webhook_secret', null);
        $this->refreshGatewayManager();
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('stripe');
        $payload = $this->stripeEvent($this->paymentIntent($payment));

        $this->postSigned('api/payments-gateways/callback/' . $payment->getKey(), $payload, $this->signature($payload))
            ->assertStatus(400);

        $this->assertNotPaid($payment);
    }

    public function testSignedStripeEventThatIsNotSucceededDoesNotMarkPaymentPaid(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('stripe');

        foreach (['processing', 'requires_action', 'requires_payment_method'] as $status) {
            $payload = $this->stripeEvent($this->paymentIntent($payment, ['status' => $status]), 'payment_intent.processing');
            $this->postSigned('api/payments-gateways/callback/' . $payment->getKey(), $payload, $this->signature($payload))
                ->assertStatus(400);
        }

        $this->assertNotPaid($payment);
    }

    public function testSignedStripeEventForAnotherPaymentOrAmountDoesNotMarkPaymentPaid(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('stripe');
        $other = $this->createPendingPayment('stripe', 100);

        $cases = [
            $this->paymentIntent($payment, ['metadata' => ['payment_id' => (string) $other->getKey()]]),
            $this->paymentIntent($payment, ['amount' => 100]),
            $this->paymentIntent($payment, ['currency' => 'eur']),
        ];

        foreach ($cases as $intent) {
            $payload = $this->stripeEvent($intent);
            $this->postSigned('api/payments-gateways/callback/' . $payment->getKey(), $payload, $this->signature($payload))
                ->assertStatus(400);
        }

        $this->assertNotPaid($payment);
    }

    public function testVerifiedStripeEventMarksPaymentPaidOnce(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('stripe');
        $payload = $this->stripeEvent($this->paymentIntent($payment));

        $this->postSigned('api/payments-gateways/callback/' . $payment->getKey(), $payload, $this->signature($payload))
            ->assertOk();
        $this->postSigned('api/payments-gateways/callback/' . $payment->getKey(), $payload, $this->signature($payload))
            ->assertOk();

        $payment->refresh();
        $this->assertEquals(PaymentStatus::PAID, $payment->status->value);
        $this->assertEquals('pi_test_123', $payment->gateway_order_id);
        Event::assertDispatchedTimes(PaymentSuccess::class, 1);
    }

    public function testStripeWebhookEndpointRequiresSignatureAndSettlesTheReferencedPayment(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('stripe');
        $payload = $this->stripeEvent($this->paymentIntent($payment));

        $this->postSigned('api/payments-gateways/webhook/stripe', $payload, null)->assertStatus(400);
        $this->postSigned('api/payments-gateways/webhook/stripe', $payload, $this->signature($payload, 'whsec_other'))->assertStatus(400);
        $this->assertNotPaid($payment);

        $this->postSigned('api/payments-gateways/webhook/stripe', $payload, $this->signature($payload))->assertOk();

        $this->assertEquals(PaymentStatus::PAID, $payment->refresh()->status->value);
        Event::assertDispatchedTimes(PaymentSuccess::class, 1);
    }

    public function testStripeCallbackWithPaymentIntentIdTrustsOnlyTheStripeApi(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('stripe');

        Http::fake([
            'api.stripe.com/v1/payment_intents/pi_unpaid' => Http::response($this->paymentIntent($payment, ['id' => 'pi_unpaid', 'status' => 'requires_payment_method'])),
            'api.stripe.com/v1/payment_intents/pi_unknown' => Http::response(['error' => ['message' => 'No such payment_intent']], 404),
            'api.stripe.com/v1/payment_intents/pi_paid' => Http::response($this->paymentIntent($payment, ['id' => 'pi_paid'])),
        ]);

        // The request claims success, Stripe says it is unpaid.
        $this->postJson('api/payments-gateways/callback/' . $payment->getKey() . '?payment_intent=pi_unpaid&redirect_status=succeeded')
            ->assertStatus(400);
        $this->postJson('api/payments-gateways/callback/' . $payment->getKey() . '?payment_intent=pi_unknown')
            ->assertStatus(400);
        $this->assertNotPaid($payment);

        $this->postJson('api/payments-gateways/callback/' . $payment->getKey() . '?payment_intent=pi_paid')->assertOk();

        $this->assertEquals(PaymentStatus::PAID, $payment->refresh()->status->value);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk_test_key'));
    }

    public function testStripeSignatureVerification(): void
    {
        $payload = '{"id":"evt_1"}';
        $now = 1700000000;

        $this->assertTrue(StripeDriver::isValidSignature($payload, $this->signature($payload, 'secret', $now), 'secret', 300, $now));
        $this->assertFalse(StripeDriver::isValidSignature($payload, $this->signature($payload, 'secret', $now), 'other', 300, $now));
        $this->assertFalse(StripeDriver::isValidSignature($payload . ' ', $this->signature($payload, 'secret', $now), 'secret', 300, $now));
        $this->assertFalse(StripeDriver::isValidSignature($payload, $this->signature($payload, 'secret', $now - 301), 'secret', 300, $now));
        $this->assertFalse(StripeDriver::isValidSignature($payload, null, 'secret', 300, $now));
        $this->assertFalse(StripeDriver::isValidSignature($payload, 'garbage', 'secret', 300, $now));
    }

    public function testPrzelewy24CallbackRequiresAValidSignatureForThisPayment(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('przelewy24', 1000, Currency::PLN);

        $notification = [
            'merchantId' => 11111,
            'posId' => 11111,
            'sessionId' => 'order-1_' . $payment->getKey() . $payment->created_at->timestamp,
            'amount' => 1000,
            'originAmount' => 1000,
            'currency' => 'PLN',
            'orderId' => 123456,
            'methodId' => 25,
            'statement' => 'p24-statement',
        ];
        $sign = fn (array $data) => $data + ['sign' => Przelewy24::createSignature([
            'merchantId' => $data['merchantId'],
            'posId' => $data['posId'],
            'sessionId' => $data['sessionId'],
            'amount' => $data['amount'],
            'originAmount' => $data['originAmount'],
            'currency' => $data['currency'],
            'orderId' => $data['orderId'],
            'methodId' => $data['methodId'],
            'statement' => $data['statement'],
            'crc' => 'p24_crc',
        ])];

        // No signature.
        $this->postJson('api/payments-gateways/callback/' . $payment->getKey(), $notification)->assertStatus(400);
        // Signature made with another CRC.
        $this->postJson('api/payments-gateways/callback/' . $payment->getKey(), $notification + ['sign' => hash('sha384', 'forged')])
            ->assertStatus(400);
        // Validly signed notification of another transaction (session) or amount.
        $this->postJson('api/payments-gateways/callback/' . $payment->getKey(), $sign(['sessionId' => 'other-session'] + $notification))
            ->assertStatus(400);
        $this->postJson('api/payments-gateways/callback/' . $payment->getKey(), $sign(['amount' => 1, 'originAmount' => 1] + $notification))
            ->assertStatus(400);

        $this->assertNotPaid($payment);
        $this->assertEquals(PaymentStatus::REQUIRES_REDIRECT, $payment->status->value);
    }

    public function testPrzelewy24RefundCallbackWithoutValidParametersDoesNotChangePayment(): void
    {
        $payment = $this->createPendingPayment('przelewy24', 1000, Currency::PLN);
        $payment->update(['status' => PaymentStatus::PAID, 'refund' => true, 'gateway_request_id' => 'req-1', 'gateway_refunds_uuid' => 'uuid-1']);

        $this->postJson('api/payments-gateways/callback/refund/' . $payment->getKey(), ['requestId' => 'req-1', 'refundsUuid' => 'uuid-1'])
            ->assertStatus(400);
        $this->postJson('api/payments-gateways/callback/refund/' . $payment->getKey(), ['requestId' => 'x', 'refundsUuid' => 'y'])
            ->assertStatus(400);

        $this->assertEquals(PaymentStatus::PAID, $payment->refresh()->status->value);
    }

    public function testFreeDriverOnlySettlesZeroAmountPayments(): void
    {
        Event::fake([PaymentSuccess::class]);
        $payment = $this->createPendingPayment('free', 1000);

        $this->postJson('api/payments-gateways/callback/' . $payment->getKey())->assertStatus(400);
        $this->assertNotPaid($payment);

        $processor = Payments::processPayment($payment);
        $this->expectException(PaymentException::class);
        try {
            $processor->purchase();
        } finally {
            $this->assertNotPaid($payment);
        }
    }

    public function testRevenueCatIsDisabledByDefaultAndNotSelectable(): void
    {
        $defaults = require __DIR__ . '/../../src/config.php';
        $this->assertFalse((bool) $defaults['drivers']['revenuecat']['enabled']);

        Config::set('ulams_payments.drivers.revenuecat.enabled', false);
        $this->refreshGatewayManager();
        Event::fake([PaymentSuccess::class]);

        $this->assertFalse(Payments::isDriverEnabled('revenuecat'));

        $payable = new Payable(1000, Currency::USD(), 'Course', 'order-2');
        $payable->setUser($this->createBillableStudent());
        $processor = $payable->process();

        try {
            $processor->purchase(['gateway' => 'revenuecat']);
        } catch (\Throwable $exception) {
            // The default gateway (Stripe) is used instead and fails without a real API key.
        }

        $this->assertNotEquals('revenuecat', $processor->getPayment()->refresh()->driver);
        $this->assertNotPaid($processor->getPayment());
    }

    public function testRevenueCatPurchaseIsRefusedWithoutReceiptVerification(): void
    {
        Config::set('ulams_payments.drivers.revenuecat.enabled', true);
        Config::set('ulams_payments.drivers.revenuecat.receipt_verifier', null);
        $this->refreshGatewayManager();
        Event::fake([PaymentSuccess::class]);

        $payable = new Payable(1000, Currency::USD(), 'Course', 'order-3');
        $payable->setUser($this->createBillableStudent());
        $processor = $payable->process();

        try {
            $processor->purchase(['gateway' => 'revenuecat']);
            $this->fail('RevenueCat purchase without verification must be refused');
        } catch (PaymentException $exception) {
            $this->assertStringContainsString('receipt verification', $exception->getMessage());
        }

        $this->assertEquals(PaymentStatus::FAILED, $processor->getPayment()->refresh()->status->value);
        Event::assertNotDispatched(PaymentSuccess::class);
    }

    public function testRevenueCatPurchaseUsesTheConfiguredReceiptVerifier(): void
    {
        Config::set('ulams_payments.drivers.revenuecat.enabled', true);
        Config::set('ulams_payments.drivers.revenuecat.receipt_verifier', FakeReceiptVerifier::class);
        $this->refreshGatewayManager();
        Event::fake([PaymentSuccess::class]);

        FakeReceiptVerifier::$result = false;
        $payable = new Payable(1000, Currency::USD(), 'Course', 'order-4');
        $payable->setUser($this->createBillableStudent());
        $processor = $payable->process();
        try {
            $processor->purchase(['gateway' => 'revenuecat']);
        } catch (PaymentException $exception) {
        }
        $this->assertEquals(PaymentStatus::FAILED, $processor->getPayment()->refresh()->status->value);
        Event::assertNotDispatched(PaymentSuccess::class);

        FakeReceiptVerifier::$result = true;
        $processor = $payable->process();
        $processor->purchase(['gateway' => 'revenuecat']);
        $this->assertEquals(PaymentStatus::PAID, $processor->getPayment()->refresh()->status->value);
    }

    public function testPaymentGatewayFakeStillSettlesCallbacks(): void
    {
        PaymentGateway::fake();
        $payment = $this->createPendingPayment('stripe');

        Payments::processPayment($payment)->callback(request());

        $this->assertEquals(PaymentStatus::PAID, $payment->refresh()->status->value);
    }
}

class FakeReceiptVerifier implements ReceiptVerifier
{
    public static bool $result = false;

    public function verify(Payment $payment, array $parameters = []): bool
    {
        return self::$result;
    }
}
