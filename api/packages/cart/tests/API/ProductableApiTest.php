<?php

namespace Ulams\Cart\Tests\API;

use Ulams\Cart\Database\Seeders\CartPermissionSeeder;
use Ulams\Cart\Enums\PeriodEnum;
use Ulams\Cart\Enums\ProductType;
use Ulams\Cart\Facades\Shop;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Cart\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;

class ProductableApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private ProductServiceContract $productService;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(CartPermissionSeeder::class);
        Shop::registerProductableClass(ExampleProductable::class);

        $this->productService = app(ProductServiceContract::class);

        Carbon::setTestNow(Carbon::now()->startOfDay());
    }

    public function testAttachProductableToUserSubscriptionAllInActive(): void
    {
        $user = $this->makeStudent();
        $productable = ExampleProductable::factory()->create();
        $subscription = Product::factory()->subscriptionWithoutTrial(ProductType::SUBSCRIPTION_ALL_IN)->create();
        $this->productService->attachProductToUser($subscription, $user);
        $endDate = PeriodEnum::calculatePeriod(Carbon::now(), $subscription->subscription_period, $subscription->subscription_duration);

        $this->actingAs($user, 'api')
            ->postJson('api/productables/attach', [
                'productable_id' => $productable->getKey(),
                'productable_type' => ExampleProductable::class,
            ])
            ->assertOk();

        $this->assertDatabaseHas(ExampleProductable::getUsersTableName(), [
            'test_productable_id' => $productable->getKey(),
            'user_id' => $user->getKey(),
            'end_date' => $endDate,
        ]);
    }

    public function testAttachProductableToUserTrialSubscriptionAllInActive(): void
    {
        $user = $this->makeStudent();
        $productable = ExampleProductable::factory()->create();
        $subscription = Product::factory()->subscriptionWithTrial(ProductType::SUBSCRIPTION_ALL_IN)->create();
        $this->productService->attachProductToUser($subscription, $user);
        $endDate = PeriodEnum::calculatePeriod(Carbon::now(), $subscription->trial_period, $subscription->trial_duration);

        $this->actingAs($user, 'api')
            ->postJson('api/productables/attach', [
                'productable_id' => $productable->getKey(),
                'productable_type' => ExampleProductable::class,
            ])
            ->assertOk();

        $this->assertDatabaseHas(ExampleProductable::getUsersTableName(), [
            'test_productable_id' => $productable->getKey(),
            'user_id' => $user->getKey(),
            'end_date' => $endDate,
        ]);
    }

    public function testAttachProductableToUserSubscriptionAllInInactive(): void
    {
        $user = $this->makeStudent();
        $productable = ExampleProductable::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/productables/attach', [
                'productable_id' => $productable->getKey(),
                'productable_type' => ExampleProductable::class,
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'You do not have an active subscription'
            ]);
    }

    public function testAttachProductableToUserProductableNotFound(): void
    {
        $user = $this->makeStudent();
        $id = 123;

        $this->actingAs($user, 'api')
            ->postJson('api/productables/attach', [
                'productable_id' => $id,
                'productable_type' => ExampleProductable::class,
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Product with id ' . $id . ' and class ' . ExampleProductable::class . ' must exist.'
            ]);
    }

    public function testAttachProductableToUserForbidden(): void
    {
        $user = config('auth.providers.users.model')::factory()->create();
        $productable = ExampleProductable::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/productables/attach', [
                'productable_id' => $productable->getKey(),
                'productable_type' => ExampleProductable::class,
            ])
            ->assertForbidden();
    }

    public function testAttachProductableToUserUnauthorized(): void
    {
        $productable = ExampleProductable::factory()->create();

        $this->postJson('api/productables/attach', [
            'productable_id' => $productable->getKey(),
            'productable_type' => ExampleProductable::class,
        ])
            ->assertUnauthorized();
    }
}
