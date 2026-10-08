<?php

namespace Ulams\Cart\Tests\Services;

use Carbon\Carbon;
use Ulams\Cart\Database\Seeders\CartPermissionSeeder;
use Ulams\Cart\Facades\Shop;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductProductable;
use Ulams\Cart\Services\ShopService;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Cart\Tests\TestCase;
use Ulams\Cart\Tests\Traits\CreatesPaymentMethods;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

class ShopServiceTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesPaymentMethods;

    private User $user;
    private TestResponse $response;
    private ShopService $shopService;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(CartPermissionSeeder::class);
        Shop::registerProductableClass(ExampleProductable::class);

        $this->shopService = app(ShopService::class);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole(UserRole::STUDENT);
    }

    public function test_abandoned_carts()
    {
        $user = $this->user;
        /** @var Product $product */
        $product = Product::factory()->single()->create();
        $productable = ExampleProductable::factory()->create();
        $product->productables()->save(new ProductProductable([
            'productable_type' => ExampleProductable::class,
            'productable_id' => $productable->getKey()
        ]));

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products', [
            'id' => $product->getKey()
        ]);
        $this->response->assertOk();

        $this->assertNotNull($user->cart->getKey());
        $this->assertContains($product->getKey(), $user->cart->items->pluck('buyable_id')->toArray());

        $abandonedCarts = $this->shopService->getAbandonedCarts(Carbon::now()->subHours(48), Carbon::now());

        $this->assertEquals(1, $abandonedCarts->count());
    }

    public function test_empty_abandoned_carts()
    {
        $user = $this->user;
        /** @var Product $product */
        $product = Product::factory()->single()->create();
        $productable = ExampleProductable::factory()->create();
        $product->productables()->save(new ProductProductable([
            'productable_type' => ExampleProductable::class,
            'productable_id' => $productable->getKey()
        ]));

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products', [
            'id' => $product->getKey()
        ]);
        $this->response->assertOk();

        $this->assertNotNull($user->cart->getKey());
        $this->assertContains($product->getKey(), $user->cart->items->pluck('buyable_id')->toArray());

        $abandonedCarts = $this->shopService->getAbandonedCarts(Carbon::now()->subHours(48), Carbon::now()->subHours(24));

        $this->assertEquals(0, $abandonedCarts->count());
    }
}
