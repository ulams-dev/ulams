<?php

namespace Ulams\Cart\Tests\API;

use Ulams\Cart\Database\Seeders\CartPermissionSeeder;
use Ulams\Cart\Enums\ProductType;
use Ulams\Cart\Events\ProductBought;
use Ulams\Cart\Facades\Shop;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductProductable;
use Ulams\Cart\Services\Contracts\ShopServiceContract;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Cart\Tests\TestCase;
use Ulams\Cart\Tests\Traits\CreatesPaymentMethods;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Models\User;
use Ulams\Payments\Facades\PaymentGateway;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

class PaymentApiTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesPaymentMethods;

    private User $user;
    private TestResponse $response;
    private ShopServiceContract $shopService;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(CartPermissionSeeder::class);
        Shop::registerProductableClass(ExampleProductable::class);

        $this->shopService = app(ShopServiceContract::class);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole(UserRole::STUDENT);
    }

    protected function createProductForTesting(): Product
    {
        $product = Product::factory()->single()->create();
        $productable = ExampleProductable::factory()->create();
        $product->productables()->save(new ProductProductable([
            'productable_type' => $productable->getMorphClass(),
            'productable_id' => $productable->getKey()
        ]));
        return $product;
    }

    public function test_pay(): void
    {
        $eventFake = Event::fake(ProductBought::class);
        $paymentsFake = PaymentGateway::fake();

        $user = $this->user;

        /** @var Product $product */
        $product = Product::factory()->create([
            'price' => 1000,
            'purchasable' => true,
        ]);

        $cart = $this->shopService->cartForUser($user);
        $this->shopService->addProductToCart($cart, $product);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/pay');
        $this->response->assertCreated();

        $eventFake->assertDispatched(ProductBought::class, fn(ProductBought $event) => $event->getProduct()->getKey() === $product->getKey());

        $product->refresh();

        $this->assertTrue($product->getOwnedByUserAttribute($user));
    }

    public function test_pay_product(): void
    {
        Event::fake(ProductBought::class);
        PaymentGateway::fake();

        $user = $this->user;

        /** @var Product $product */
        $product = Product::factory()->create([
            'price' => 1000,
            'purchasable' => true,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/product/' . $product->getKey() . '/pay')
            ->assertCreated();

        Event::assertDispatched(ProductBought::class, fn(ProductBought $event) => $event->getProduct()->getKey() === $product->getKey());

        $product->refresh();

        $this->assertTrue($product->getOwnedByUserAttribute($user));
    }

    public function test_pay_subscription(): void
    {
        Event::fake(ProductBought::class);
        PaymentGateway::fake();

        $user = $this->user;

        /** @var Product $product */
        $product = Product::factory()
            ->subscriptionWithoutTrial()
            ->create([
                'price' => 1000,
                'purchasable' => true,
            ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/product/' . $product->getKey() . '/pay')
            ->assertCreated()
            ->assertJsonFragment([
                'amount' => 1000
            ]);

        Event::assertDispatched(ProductBought::class, fn(ProductBought $event) => $event->getProduct()->getKey() === $product->getKey() && $event->getProduct()->type === ProductType::SUBSCRIPTION);

        $product->refresh();

        $this->assertTrue($product->getOwnedByUserAttribute($user));
    }

    public function test_pay_subscription_with_trial(): void
    {
        Event::fake(ProductBought::class);
        PaymentGateway::fake();

        $user = $this->user;

        /** @var Product $product */
        $product = Product::factory()
            ->subscriptionWithTrial()
            ->create([
                'price' => 1000,
                'purchasable' => true,
            ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/product/' . $product->getKey() . '/pay')
            ->assertCreated()
            ->assertJsonFragment([
                'amount' => 100
            ]);

        Event::assertDispatched(ProductBought::class, fn(ProductBought $event) => $event->getProduct()->getKey() === $product->getKey() && $event->getProduct()->type === ProductType::SUBSCRIPTION);

        $product->refresh();

        $this->assertTrue($product->getOwnedByUserAttribute($user));

        $this->actingAs($user, 'api')
            ->postJson('/api/product/' . $product->getKey() . '/pay')
            ->assertCreated()
            ->assertJsonFragment([
                'amount' => 1000
            ]);
    }

    public function test_pay_for_free_products(): void
    {
        $eventFake = Event::fake(ProductBought::class);

        $user = $this->user;

        /** @var Product $product */
        $product = Product::factory()->create([
            'price' => 0,
            'purchasable' => true,
        ]);

        $cart = $this->shopService->cartForUser($user);
        $this->shopService->addProductToCart($cart, $product);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/pay');
        $this->response->assertCreated();

        $eventFake->assertDispatched(ProductBought::class, fn(ProductBought $event) => $event->getProduct()->getKey() === $product->getKey());

        $product->refresh();

        $this->assertTrue($product->getOwnedByUserAttribute($user));
    }
}
