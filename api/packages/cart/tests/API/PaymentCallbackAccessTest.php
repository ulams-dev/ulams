<?php

namespace Ulams\Cart\Tests\API;

use Ulams\Cart\Database\Seeders\CartPermissionSeeder;
use Ulams\Cart\Enums\OrderStatus;
use Ulams\Cart\Events\ProductBought;
use Ulams\Cart\Facades\Shop;
use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductProductable;
use Ulams\Cart\Services\Contracts\OrderServiceContract;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Cart\Tests\TestCase;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Models\User;
use Ulams\Payments\Enums\PaymentStatus;
use Ulams\Payments\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;

/**
 * A forged payment callback must not mark the order paid or grant access to the product.
 */
class PaymentCallbackAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_SECRET = 'whsec_cart_test';

    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        Config::set('ulams_payments.drivers.stripe.enabled', true);
        Config::set('ulams_payments.drivers.stripe.secret_key', 'sk_test_key');
        Config::set('ulams_payments.drivers.stripe.publishable_key', 'pk_test_key');
        Config::set('ulams_payments.drivers.stripe.webhook_secret', self::WEBHOOK_SECRET);
        $this->app->forgetInstance('payment-gateway');
        Facade::clearResolvedInstance('payment-gateway');

        $this->seed(CartPermissionSeeder::class);
        Shop::registerProductableClass(ExampleProductable::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole(UserRole::STUDENT);
    }

    /**
     * @return array{0: Product, 1: Order, 2: Payment}
     */
    private function pendingStripeOrder(): array
    {
        $product = Product::factory()->single()->create(['price' => 1000, 'extra_fees' => 0, 'purchasable' => true]);
        $productable = ExampleProductable::factory()->create();
        $product->productables()->save(new ProductProductable([
            'productable_type' => $productable->getMorphClass(),
            'productable_id' => $productable->getKey(),
        ]));

        $order = app(OrderServiceContract::class)->createOrderFromProduct($product, $this->user->getKey());
        $payment = $order->process()->getPayment();
        // State after a Stripe purchase that needs 3-D Secure.
        $payment->update(['driver' => 'stripe', 'status' => PaymentStatus::REQUIRES_REDIRECT]);

        return [$product, $order, $payment->refresh()];
    }

    private function signedEvent(Payment $payment): array
    {
        $payload = json_encode([
            'id' => 'evt_cart_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_cart_1',
                'object' => 'payment_intent',
                'amount' => $payment->amount,
                'currency' => strtolower((string) $payment->currency),
                'status' => 'succeeded',
                'metadata' => ['payment_id' => (string) $payment->getKey()],
            ]],
        ]);
        $timestamp = time();

        return [$payload, 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, self::WEBHOOK_SECRET)];
    }

    public function test_forged_callback_does_not_grant_access(): void
    {
        Event::fake([ProductBought::class]);
        [$product, $order, $payment] = $this->pendingStripeOrder();

        $this->postJson('/api/payments-gateways/callback/' . $payment->getKey())->assertStatus(400);
        $this->postJson('/api/payments-gateways/callback/' . $payment->getKey(), ['status' => 'succeeded'], ['Stripe-Signature' => 't=1,v1=forged'])
            ->assertStatus(400);

        $this->assertEquals(OrderStatus::PROCESSING, $order->refresh()->status);
        $this->assertNotEquals(PaymentStatus::PAID, $payment->refresh()->status->value);
        $this->assertFalse($product->refresh()->getOwnedByUserAttribute($this->user));
        Event::assertNotDispatched(ProductBought::class);
    }

    public function test_verified_callback_grants_access(): void
    {
        Event::fake([ProductBought::class]);
        [$product, $order, $payment] = $this->pendingStripeOrder();
        [$payload, $signature] = $this->signedEvent($payment);

        $this->call('POST', '/api/payments-gateways/webhook/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)->assertOk();

        $this->assertEquals(OrderStatus::PAID, $order->refresh()->status);
        $this->assertTrue($product->refresh()->getOwnedByUserAttribute($this->user));
        Event::assertDispatched(ProductBought::class);
    }
}
