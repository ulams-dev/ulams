<?php

namespace EscolaLms\Payments\Tests\Api;

use EscolaLms\Payments\Enums\Currency;
use EscolaLms\Payments\Enums\PaymentStatus;
use EscolaLms\Payments\Events\PaymentFailed;
use EscolaLms\Payments\Events\PaymentRegistered;
use EscolaLms\Payments\Exceptions\CardDeclined;
use EscolaLms\Payments\Exceptions\ExpiredCard;
use EscolaLms\Payments\Exceptions\IncorrectCvc;
use EscolaLms\Payments\Exceptions\PaymentException;
use EscolaLms\Payments\Exceptions\ProcessingError;
use EscolaLms\Payments\Facades\PaymentGateway;
use EscolaLms\Payments\Tests\Mocks\Payable;
use EscolaLms\Payments\Tests\TestCase;
use EscolaLms\Payments\Tests\Traits\CreatesBillable;
use EscolaLms\Payments\Tests\Traits\CreatesPaymentMethods;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class PaymentProcessingTest extends TestCase
{
    use CreatesPaymentMethods;
    use CreatesBillable;

    public function testPayableCanBecomePayment(): void
    {
        Event::fake([PaymentRegistered::class]);
        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $processor = $payable->process();
        $payment = $processor->getPayment();
        Event::assertDispatched(PaymentRegistered::class);
        $this->assertEquals($payable->getPaymentAmount(), $payment->amount);
        $this->assertEquals($payable->getPaymentDescription(), $payment->description);
        $this->assertEquals($payable->getPaymentOrderId(), $payment->order_id);
        $this->assertEquals($payable->getPaymentCurrency(), $payment->currency);
        $this->assertEquals($payable->getUser()->getKey(), $payment->user->getKey());
    }

    public function testPayableCanBecomePaymentAndBePaidUsingMockedStripe(): void
    {
        PaymentGateway::fake();

        Event::fake([PaymentRegistered::class]);
        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $processor = $payable->process();
        Event::assertDispatched(PaymentRegistered::class);
        $payment = $processor->getPayment();
        $this->assertEquals(PaymentStatus::NEW(), $payment->status);


        try {
            $processor->purchase(['gateway' => 'stripe']);
        } catch (Exception $ex) {
            $this->assertEquals('Missing Payment Gateway parameters: return_url, payment_method', $ex->getMessage());
        }

        $processor->purchase(['gateway' => 'stripe', 'return_url' => 'https://localhost.test', 'payment_method' => '123']);
        $payment->refresh();

        $this->assertEquals(PaymentStatus::PAID(), $payment->status);

        $this->assertTrue($processor->isSuccessful());
        $this->assertFalse($processor->isCancelled());
        $this->assertFalse($processor->isRedirect());
        $this->assertFalse($processor->isNew());

        $response = $this->actingAs($billable)->json('GET', 'api/payments/');
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $payment->getKey()
        ]);
    }

    public function testPayableCanBecomePaymentAndBePaidUsingMockedP24(): void
    {
        PaymentGateway::fake();

        Event::fake([PaymentRegistered::class]);
        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $processor = $payable->process();
        Event::assertDispatched(PaymentRegistered::class);
        $payment = $processor->getPayment();
        $this->assertEquals(PaymentStatus::NEW(), $payment->status);

        try {
            $processor->purchase(['gateway' => 'przelewy24']);
        } catch (Exception $ex) {
            $this->assertEquals('Missing Payment Gateway parameters: return_url, email', $ex->getMessage());
        }

        $processor->purchase(['gateway' => 'przelewy24', 'email' => 'test@localhost', 'return_url' => 'https://localhost.test']);
        $payment->refresh();

        $this->assertEquals(PaymentStatus::PAID(), $payment->status);

        $this->assertTrue($processor->isSuccessful());
        $this->assertFalse($processor->isCancelled());
        $this->assertFalse($processor->isRedirect());
        $this->assertFalse($processor->isNew());

        $response = $this->actingAs($billable)->json('GET', 'api/payments/');
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $payment->getKey()
        ]);
    }

    public function testPayableCanBecomePaymentAndBePaidUsingStripe(): void
    {
        $this->markTestSkipped(
            'This test calls external (Stripe) api, we probably should not run it automatically every time we run test suite'
        );

        Event::fake([PaymentRegistered::class]);
        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $processor = $payable->process()->setPaymentDriverName('stripe');
        Event::assertDispatched(PaymentRegistered::class);
        $payment = $processor->getPayment();
        $this->assertEquals(PaymentStatus::NEW(), $payment->status);

        $paymentMethodId = $this->getPaymentMethodId();

        $processor->purchase(['payment_method' => $paymentMethodId, 'return_url' => url('/')]);
        $payment->refresh();

        $this->assertEquals(PaymentStatus::PAID(), $payment->status);

        $response = $this->actingAs($billable)->json('GET', 'api/payments/');
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $payment->getKey()
        ]);
    }

    public function testPayableCanBecomePaymentAndWillRequireRedirectUsingPrzelewy24(): void
    {
        $this->markTestSkipped(
            'This test calls external (Przelewy24) api, we probably should not run it automatically every time we run test suite'
        );

        Event::fake([PaymentRegistered::class]);
        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $processor = $payable->process()->setPaymentDriverName('przelewy24');
        Event::assertDispatched(PaymentRegistered::class);
        $payment = $processor->getPayment();
        $this->assertEquals(PaymentStatus::NEW(), $payment->status);

        $processor->purchase(['return_url' => url('/'), 'email' => $billable->email]);
        $payment->refresh();

        $this->assertEquals(PaymentStatus::REQUIRES_REDIRECT(), $payment->status);

        $response = $this->actingAs($billable)->json('GET', 'api/payments/');
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $payment->getKey()
        ]);
    }

    public function testPaymentShouldFailAndThrowExceptionUsingStripe(): void
    {
        $this->markTestSkipped(
            'This test calls external (Stripe) api, we probably should not run it automatically every time we run test suite'
        );

        Event::fake();
        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $wrongCardNumbers = [
            '4000000000000002' => CardDeclined::class,
            '4000000000000069' => ExpiredCard::class,
            '4000000000000101' => IncorrectCvc::class,
            '4000000000000119' => ProcessingError::class,
            '4000000000000127' => IncorrectCvc::class,
            '4000000000009979' => CardDeclined::class,
            '4000000000009987' => CardDeclined::class,
            '4000000000009995' => CardDeclined::class,
        ];

        foreach ($wrongCardNumbers as $wrongCardNumber => $expectedException) {
            $processor = $payable->process()->setPaymentDriverName('stripe');
            $payment = $processor->getPayment();
            $this->assertEquals(PaymentStatus::NEW(), $payment->status);
            $paymentMethodId = $this->getPaymentMethodId($wrongCardNumber);
            try {
                $processor->purchase(['payment_method' => $paymentMethodId, 'return_url' => url('/')]);
            } catch (PaymentException $ex) {
                $this->assertInstanceOf($expectedException, $ex);
            }
            $payment->refresh();
            $this->assertEquals(PaymentStatus::FAILED(), $payment->status);
            Event::assertDispatched(PaymentFailed::class);
        }
    }

    public function testPayableCanBecomePaymentAndBePaidUsingMockedP24WithRefund(): void
    {
        PaymentGateway::fake();

        Event::fake([PaymentRegistered::class]);
        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $processor = $payable->process();
        Event::assertDispatched(PaymentRegistered::class);
        $payment = $processor->getPayment();
        $this->assertEquals(PaymentStatus::NEW(), $payment->status);

        $processor->purchase(['gateway' => 'przelewy24', 'email' => 'test@localhost', 'return_url' => 'https://localhost.test', 'has_trial' => true]);
        $payment->refresh();

        $this->assertTrue($payment->refund);
        $this->assertEquals(PaymentStatus::PAID(), $payment->status);
    }

    public function testPayableRefundCompleteP24Fake(): void
    {
        PaymentGateway::fake();

        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $processor = $payable->process();
        $payment = $processor->getPayment();
        $this->assertEquals(PaymentStatus::NEW(), $payment->status);

        $processor->purchase(['gateway' => 'przelewy24', 'email' => 'test@localhost', 'return_url' => 'https://localhost.test', 'has_trial' => true]);

        $processor->callback(new Request());

        $this->assertTrue($payment->refund);
        $this->assertNotNull($payment->gateway_request_id);
        $this->assertNotNull($payment->gateway_refunds_uuid);
        $this->assertEquals(PaymentStatus::PAID(), $payment->status);

        $processor->callbackRefund((new Request())->replace([
            'requestId' => $payment->gateway_request_id,
            'refundsUuid' => $payment->gateway_refunds_uuid
        ]));

        $this->assertEquals(PaymentStatus::REFUNDED(), $payment->status);
    }

    public function testPayableRefundInvalidDataP24Fake(): void
    {
        PaymentGateway::fake();

        $billable = $this->createBillableStudent();
        $payable = new Payable(1000, Currency::USD(), 'asdf', 1337);
        $payable->setUser($billable);

        $processor = $payable->process();
        $payment = $processor->getPayment();
        $this->assertEquals(PaymentStatus::NEW(), $payment->status);

        $processor->purchase(['gateway' => 'przelewy24', 'email' => 'test@localhost', 'return_url' => 'https://localhost.test', 'has_trial' => true]);

        $processor->callback(new Request());

        $this->assertTrue($payment->refund);
        $this->assertNotNull($payment->gateway_request_id);
        $this->assertNotNull($payment->gateway_refunds_uuid);
        $this->assertEquals(PaymentStatus::PAID(), $payment->status);

        $processor->callbackRefund((new Request())->replace([
            'requestId' => 'invalid_request_id',
            'refundsUuid' => 'invalid_refunds_uuid'
        ]));

        $this->assertEquals(PaymentStatus::FAILED(), $payment->status);
    }
}
