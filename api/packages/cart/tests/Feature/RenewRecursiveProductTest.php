<?php

namespace Ulams\Cart\Tests\Feature;

use Ulams\Cart\Enums\PeriodEnum;
use Ulams\Cart\Enums\SubscriptionStatus;
use Ulams\Cart\Jobs\RenewRecursiveProduct;
use Ulams\Cart\Jobs\RenewRecursiveProductUser;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Tests\TestCase;
use Ulams\Core\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

class RenewRecursiveProductTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow(Carbon::now()->startOfDay());
    }

    public function testRenewRecursiveProductOnlyActive(): void
    {
        $product = Product::factory()->subscriptionWithoutTrial()->state(['subscription_period' => PeriodEnum::DAILY, 'subscription_duration' => 3, 'recursive' => true])->create();
        $user1 = $this->makeStudent();
        $user2 = $this->makeStudent();
        $user3 = $this->makeStudent();
        $user4 = $this->makeStudent();
        $user5 = $this->makeStudent();

        $product->users()->sync([
            $user1->getKey() => ['end_date' => Carbon::now()->addHours(2), 'status' => SubscriptionStatus::ACTIVE],
            $user2->getKey() => ['end_date' => Carbon::now()->subHour(), 'status' => SubscriptionStatus::ACTIVE],
            $user3->getKey() => ['end_date' => Carbon::now()->subHour(), 'status' => SubscriptionStatus::CANCELLED],
            $user4->getKey() => ['end_date' => Carbon::now()->subHour(), 'status' => SubscriptionStatus::EXPIRED],
            $user5->getKey() => ['end_date' => null, 'status' => null]
        ]);

        (new RenewRecursiveProduct())->handle(app(ProductServiceContract::class));

        Queue::assertPushed(RenewRecursiveProductUser::class, 1);
    }
}
