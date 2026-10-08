<?php

namespace EscolaLms\AssignWithoutAccount\Tests\Api;

use EscolaLms\AssignWithoutAccount\Database\Seeders\AssignWithoutAccountPermissionSeeder;
use EscolaLms\AssignWithoutAccount\Events\AssignToProductable;
use EscolaLms\AssignWithoutAccount\Tests\TestCase;
use EscolaLms\Auth\Enums\SettingStatusEnum;
use EscolaLms\Auth\Events\AccountRegistered;
use EscolaLms\Auth\Models\User as AuthUser;
use EscolaLms\Cart\Contracts\Productable;
use EscolaLms\Cart\Events\ProductableAttached;
use EscolaLms\Cart\Facades\Shop;
use EscolaLms\Cart\Tests\Mocks\ExampleProductable;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class AssignToProductableTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AssignWithoutAccountPermissionSeeder::class);
        Shop::registerProductableClass(ExampleProductable::class);

        Event::fake([AssignToProductable::class, ProductableAttached::class]);
        Notification::fake();
    }

    public function testAssignToProductable(): void
    {
        $productable = ExampleProductable::factory()->create();
        $email = 'email@test.pl';

        $this->createSubmission($productable, $email);
        $user = $this->registerAccount($email);

        Event::assertDispatched(ProductableAttached::class);
        Event::assertDispatched(AssignToProductable::class);

        $this->assertCount(1, $productable->users()->get());
        $this->assertTrue($productable->users()->get()->contains('email', $user->email));
        $this->assertTrue($productable->users()->get()->contains('id', $user->getKey()));
    }

    public function testAssignToMultipleProductables(): void
    {
        $productable1 = ExampleProductable::factory()->create();
        $productable2 = ExampleProductable::factory()->create();
        $productable3 = ExampleProductable::factory()->create();
        $email = 'email@test.pl';

        $this->createSubmission($productable1, $email);
        $this->createSubmission($productable2, $email);
        $user = $this->registerAccount($email);

        Event::assertDispatched(ProductableAttached::class);
        Event::assertDispatched(AssignToProductable::class);

        $this->assertCount(1, $productable1->users()->get());
        $this->assertTrue($productable1->users()->get()->contains('email', $user->email));
        $this->assertTrue($productable1->users()->get()->contains('id', $user->getKey()));

        $this->assertCount(1, $productable2->users()->get());
        $this->assertTrue($productable2->users()->get()->contains('email', $user->email));
        $this->assertTrue($productable2->users()->get()->contains('id', $user->getKey()));
    }

    public function testMultipleAssignmentsToProductable(): void
    {
        $productable = ExampleProductable::factory()->create();
        $email1 = 'email1@test.pl';
        $email2 = 'email2@test.pl';
        $email3 = 'email3@test.pl';

        $this->createSubmission($productable, $email1);
        $this->createSubmission($productable, $email2);
        $user1 = $this->registerAccount($email1);
        $user2 = $this->registerAccount($email2);

        Event::assertDispatched(ProductableAttached::class);
        Event::assertDispatched(AssignToProductable::class);

        $this->assertCount(2, $productable->users()->get());
        $this->assertTrue($productable->users()->get()->contains('email', $user1->email));
        $this->assertTrue($productable->users()->get()->contains('email', $user2->email));
        $this->assertTrue($productable->users()->get()->contains('id', $user1->getKey()));
        $this->assertTrue($productable->users()->get()->contains('id', $user2->getKey()));
    }

    public function createSubmission(Productable $productable, string $email): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->json('POST', '/api/admin/user-submissions', [
                'email' => $email,
                'morphable_id' => $productable->getKey(),
                'morphable_type' => ExampleProductable::class,
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
