<?php

namespace EscolaLms\AssignWithoutAccount\Tests\Api;

use EscolaLms\AssignWithoutAccount\Database\Seeders\AssignWithoutAccountPermissionSeeder;
use EscolaLms\AssignWithoutAccount\Events\AssignToProduct;
use EscolaLms\AssignWithoutAccount\Tests\TestCase;
use EscolaLms\Auth\Events\AccountRegistered;
use EscolaLms\Auth\Models\User as AuthUser;
use EscolaLms\Cart\Events\ProductAttached;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class AssignToProductTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AssignWithoutAccountPermissionSeeder::class);
        Event::fake([AssignToProduct::class, ProductAttached::class]);
        Notification::fake();
    }

    public function testAssignToProduct(): void
    {
        $product = Product::factory()->create();
        $email = 'email@test.pl';

        $this->createSubmission($product, $email);
        $user = $this->registerAccount($email);

        Event::assertDispatched(ProductAttached::class);
        Event::assertDispatched(AssignToProduct::class);

        $this->assertCount(1, $product->users()->get());
        $this->assertTrue($product->users()->get()->contains('email', $user->email));
        $this->assertTrue($product->users()->get()->contains('id', $user->getKey()));
    }

    public function testAssignToMultipleProducts(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();
        $email = 'email@test.pl';

        $this->createSubmission($product1, $email);
        $this->createSubmission($product2, $email);
        $user = $this->registerAccount($email);

        Event::assertDispatched(ProductAttached::class);
        Event::assertDispatched(AssignToProduct::class);

        $this->assertCount(1, $product1->users()->get());
        $this->assertCount(1, $product2->users()->get());
        $this->assertTrue($product1->users()->get()->contains('email', $user->email));
        $this->assertTrue($product1->users()->get()->contains('id', $user->getKey()));
        $this->assertTrue($product2->users()->get()->contains('email', $user->email));
        $this->assertTrue($product2->users()->get()->contains('id', $user->getKey()));
    }

    public function testMultipleAssignmentsToProduct(): void
    {
        $product = Product::factory()->create();
        $email1 = 'email1@test.pl';
        $email2 = 'email2@test.pl';
        $email3 = 'email3@test.pl';

        $this->createSubmission($product, $email1);
        $this->createSubmission($product, $email2);
        $user1 = $this->registerAccount($email1);
        $user2 = $this->registerAccount($email2);

        Event::assertDispatched(ProductAttached::class);
        Event::assertDispatched(AssignToProduct::class);

        $this->assertCount(2, $product->users()->get());
        $this->assertTrue($product->users()->get()->contains('email', $user1->email));
        $this->assertTrue($product->users()->get()->contains('email', $user2->email));
        $this->assertTrue($product->users()->get()->contains('id', $user1->getKey()));
        $this->assertTrue($product->users()->get()->contains('id', $user2->getKey()));
    }

    public function createSubmission(Product $product, string $email): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->json('POST', '/api/admin/user-submissions', [
                'email' => $email,
                'morphable_id' => $product->getKey(),
                'morphable_type' => Product::class,
            ]);
    }

    private function registerAccount(string $email): AuthUser
    {
        $user = AuthUser::factory()->create([
            'email' => $email,
        ]);

        event(new AccountRegistered($user, $this->faker->url));

        return $user;
    }
}
