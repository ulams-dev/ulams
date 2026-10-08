<?php

namespace Ulams\Cart\Tests\Feature;

use Ulams\Cart\Enums\SubscriptionStatus;
use Ulams\Cart\Jobs\ExpireRecursiveProduct;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Tests\TestCase;
use Ulams\Core\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;

class ExpireRecursiveProductTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::now()->startOfDay());
    }

    public function testExpireRecursiveProductMarkAsExpired(): void
    {
        /** @var Product $product */
        $product = Product::factory()->subscriptionWithoutTrial()->create();
        $user1 = $this->makeStudent();
        $user2 = $this->makeStudent();
        $user3 = $this->makeStudent();
        $user4 = $this->makeStudent();
        $user5 = $this->makeStudent();

        $product->users()->sync([
            $user1->getKey() => ['end_date' => Carbon::now()->addHour(), 'status' => SubscriptionStatus::ACTIVE],
            $user2->getKey() => ['end_date' => Carbon::now()->subHour(), 'status' => SubscriptionStatus::ACTIVE],
            $user3->getKey() => ['end_date' => Carbon::now()->subHour(), 'status' => SubscriptionStatus::CANCELLED],
            $user4->getKey() => ['end_date' => Carbon::now()->subHour(), 'status' => SubscriptionStatus::EXPIRED],
            $user5->getKey() => ['end_date' => null, 'status' => null]
        ]);

        (new ExpireRecursiveProduct())->handle();

        $this->assertProductUserHas($user1, $product, SubscriptionStatus::ACTIVE);
        $this->assertProductUserHas($user2, $product, SubscriptionStatus::EXPIRED);
        $this->assertProductUserHas($user3, $product, SubscriptionStatus::CANCELLED);
        $this->assertProductUserHas($user4, $product, SubscriptionStatus::EXPIRED);
        $this->assertProductUserHas($user5, $product, null);
    }

    private function assertProductUserHas(User $user, Product $product, ?string $status): void
    {
        $this->assertDatabaseHas('products_users', [
            'user_id' => $user->getKey(),
            'product_id' => $product->getKey(),
            'status' => $status
        ]);
    }
}
