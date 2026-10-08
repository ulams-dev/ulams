<?php

namespace EscolaLms\AssignWithoutAccount\Tests\Api;

use EscolaLms\AssignWithoutAccount\Database\Seeders\AssignWithoutAccountPermissionSeeder;
use EscolaLms\AssignWithoutAccount\Enums\UserSubmissionStatusEnum;
use EscolaLms\AssignWithoutAccount\Events\AssignToProduct;
use EscolaLms\AssignWithoutAccount\Events\AssignToProductable;
use EscolaLms\AssignWithoutAccount\Events\UnassignProduct;
use EscolaLms\AssignWithoutAccount\Events\UnassignProductable;
use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use EscolaLms\AssignWithoutAccount\Tests\TestCase;
use EscolaLms\Cart\Facades\Shop;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Tests\Mocks\ExampleProductable;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

class UserSubmissionAdminTest extends TestCase
{
    use DatabaseTransactions, WithFaker, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AssignWithoutAccountPermissionSeeder::class);
    }

    public function testIndexUserSubmission(): void
    {
        $admin = $this->makeAdmin();
        UserSubmission::factory()->count(10)->create();

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions')
            ->assertOk();

        $this->assertApiIndexResponse($response, 10);
        $this->assertDatabaseCount('user_submissions', 10);
    }

    public function testIndexFilterByMorphType(): void
    {
        $admin = $this->makeAdmin();
        $morphableType = 'EscolaLms\\Test\\Models\\Test';
        UserSubmission::factory()->count(10)->create();
        UserSubmission::factory()->count(5)->create([
            'morphable_type' => $morphableType
        ]);

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions?morphable_type=' . $morphableType)
            ->assertOk();

        $this->assertApiIndexResponse($response, 5);
        $this->assertDatabaseCount('user_submissions', 15);
    }

    public function testIndexFilterByMorph(): void
    {
        $admin = $this->makeAdmin();

        $morphableId = 1;
        $morphableType = 'EscolaLms\\Test\\Models\\Test';
        UserSubmission::factory()->count(10)->create();
        UserSubmission::factory()->count(5)->create([
            'morphable_id' => $morphableId,
            'morphable_type' => $morphableType
        ]);
        UserSubmission::factory()->count(5)->create([
            'morphable_type' => $morphableType
        ]);

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions?morphable_id=' . $morphableId . '&morphable_type=' . $morphableType)
            ->assertOk();

        $this->assertApiIndexResponse($response, 5);
        $this->assertDatabaseCount('user_submissions', 20);
    }

    public function testIndexFilterByMorphId(): void
    {
        $admin = $this->makeAdmin();

        $morphableId = 1;
        UserSubmission::factory()->count(5)->create();
        UserSubmission::factory()->count(5)->create([
            'morphable_id' => $morphableId,
        ]);

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions?morphable_id=' . $morphableId)
            ->assertOk();

        $this->assertApiIndexResponse($response, 10);
        $this->assertDatabaseCount('user_submissions', 10);
    }

    public function testIndexFilterByEmail(): void
    {
        $admin = $this->makeAdmin();

        $email = 'test@test.pl';
        UserSubmission::factory()->count(10)->create();
        UserSubmission::factory()->count(5)->create([
            'email' => $email,
        ]);

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions?email=' . $email)
            ->assertOk();

        $this->assertApiIndexResponse($response, 5);
        $this->assertDatabaseCount('user_submissions', 15);
    }

    public function testIndexFilterByStatus(): void
    {
        $admin = $this->makeAdmin();
        UserSubmission::factory()->count(10)->create([
            'status' => UserSubmissionStatusEnum::ACCEPTED
        ]);
        UserSubmission::factory()->count(5)->create([
            'status' => UserSubmissionStatusEnum::SENT
        ]);

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions?status=' . UserSubmissionStatusEnum::SENT)
            ->assertOk();

        $this->assertApiIndexResponse($response, 5);
        $this->assertDatabaseCount('user_submissions', 15);
    }

    public function testIndexFilterWithOrder(): void
    {
        $admin = $this->makeAdmin();

        $submission1 = UserSubmission::factory()->create([
            'status' => UserSubmissionStatusEnum::ACCEPTED,
            'email' => 'aexample@example.com',
        ]);
        $submission2 = UserSubmission::factory()->create([
            'status' => UserSubmissionStatusEnum::SENT,
            'email' => 'bexample@example.com',
        ]);

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions',  [
                'order_by' => 'id',
                'order' => 'DESC',
            ]);

        $this->assertTrue($response->json('data')[0]['id'] === $submission2->getKey());

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions',  [
                'order_by' => 'id',
                'order' => 'ASC',
            ]);

        $this->assertTrue($response->json('data')[0]['id'] === $submission1->getKey());

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions',  [
                'order_by' => 'email',
                'order' => 'DESC',
            ]);

        $this->assertTrue($response->json('data')[0]['id'] === $submission2->getKey());

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions',  [
                'order_by' => 'email',
                'order' => 'ASC',
            ]);

        $this->assertTrue($response->json('data')[0]['id'] === $submission1->getKey());

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions',  [
                'order_by' => 'status',
                'order' => 'DESC',
            ]);

        $this->assertTrue($response->json('data')[0]['id'] === $submission2->getKey());

        $response = $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/user-submissions',  [
                'order_by' => 'status',
                'order' => 'ASC',
            ]);

        $this->assertTrue($response->json('data')[0]['id'] === $submission1->getKey());
    }

    public function testCreateUserSubmissionToProduct()
    {
        Event::fake();

        $admin = $this->makeAdmin();
        $product = Product::factory()->create();
        $email = 'test@test.pl';

        $response = $this->actingAs($admin, 'api')
            ->json('POST', '/api/admin/user-submissions', [
                'email' => $email,
                'morphable_id' => $product->getKey(),
                'morphable_type' => Product::class,
            ])
            ->assertCreated();

        $response->assertJsonFragment([
            'email' => $email,
            'morphable_id' => $product->getKey(),
            'morphable_type' => Product::class,
            'status' => UserSubmissionStatusEnum::SENT,
        ]);
        $this->assertDatabaseHas('user_submissions', [
            'email' => $email,
            'morphable_id' => $product->getKey(),
            'morphable_type' => Product::class,
            'status' => UserSubmissionStatusEnum::SENT
        ]);

        Event::assertDispatched(AssignToProduct::class);
        Event::assertNotDispatched(AssignToProductable::class);
    }

    public function testCreateUserSubmissionToProductable()
    {
        Event::fake();
        Shop::registerProductableClass(ExampleProductable::class);

        $exampleProductable = ExampleProductable::factory()->create();
        $admin = $this->makeAdmin();
        $email = 'test@test.pl';

        $response = $this->actingAs($admin, 'api')
            ->json('POST', '/api/admin/user-submissions', [
                'email' => $email,
                'morphable_id' => $exampleProductable->getKey(),
                'morphable_type' => ExampleProductable::class,
            ])
            ->assertCreated();

        $response->assertJsonFragment([
            'email' => $email,
            'morphable_id' => $exampleProductable->getKey(),
            'morphable_type' => ExampleProductable::class,
            'status' => UserSubmissionStatusEnum::SENT,
        ]);
        $this->assertDatabaseHas('user_submissions', [
            'email' => $email,
            'morphable_id' => $exampleProductable->getKey(),
            'morphable_type' => ExampleProductable::class,
            'status' => UserSubmissionStatusEnum::SENT
        ]);

        Event::assertDispatched(AssignToProductable::class);
        Event::assertNotDispatched(AssignToProduct::class);
    }

    public function testCreateUserSubmissionToNotRegisteredProductable()
    {
        Event::fake();

        $exampleProductable = ExampleProductable::factory()->create();
        $admin = $this->makeAdmin();
        $email = 'test@test.pl';

        $this->actingAs($admin, 'api')
            ->json('POST', '/api/admin/user-submissions', [
                'email' => $email,
                'morphable_id' => $exampleProductable->getKey(),
                'morphable_type' => ExampleProductable::class,
            ]);

        Event::assertNotDispatched(AssignToProductable::class);
    }

    public function testCreateUserSubmissionInvalidData()
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->json('POST', '/api/admin/user-submissions', [])
            ->assertUnprocessable();
    }

    public function testCreateUserSubmissionUnauthorizedUser()
    {
        $this->json('POST', '/api/admin/user-submissions', [])
            ->assertUnauthorized();
    }

    public function updateUserSubmissionDataProvider(): array
    {
        return [
            [fn() => [Product::class, AssignToProduct::class]],
            [function () {
                Shop::registerProductableClass(ExampleProductable::class);
                return [ExampleProductable::class, AssignToProductable::class];
            }],
        ];
    }

    /**
     * @dataProvider updateUserSubmissionDataProvider
     */
    public function testUpdateUserSubmission($data): void
    {
        Event::fake();

        [$class, $event] = $data();

        $model = $class::factory()->create();
        $userSubmission = UserSubmission::factory()->create();
        $admin = $this->makeAdmin();
        $email = 'test@test.pl';

        $response = $this->actingAs($admin, 'api')
            ->json('PUT', '/api/admin/user-submissions/' . $userSubmission->getKey(), [
                'email' => $email,
                'morphable_id' => $model->getKey(),
                'morphable_type' => $class,
                'status' => UserSubmissionStatusEnum::REJECTED
            ])
            ->assertOk();

        $response->assertJsonFragment([
            'email' => $email,
            'morphable_id' => $model->getKey(),
            'morphable_type' => $class,
            'status' => UserSubmissionStatusEnum::REJECTED,
        ]);

        Event::assertNotDispatched($event);
    }

    /**
     * @dataProvider updateUserSubmissionDataProvider
     */
    public function testUpdateNotExistingUserSubmission($data): void
    {
        Event::fake();

        [$class, $event] = $data();

        $model = $class::factory()->create();
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->json('PUT', '/api/admin/user-submissions/1', [
                'email' => 'test@test.pl',
                'morphable_id' => $model->getKey(),
                'morphable_type' => $class,
                'status' => UserSubmissionStatusEnum::REJECTED
            ])
            ->assertNotFound();

        Event::assertNotDispatched($event);
    }

    /**
     * @dataProvider updateUserSubmissionDataProvider
     */
    public function testUpdateUserSubmissionInvalidData($data): void
    {
        Event::fake();

        [$class, $event] = $data();

        $userSubmission = UserSubmission::factory()->create();
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->json('PUT', '/api/admin/user-submissions/' . $userSubmission->getKey(), [])
            ->assertUnprocessable();

        Event::assertNotDispatched($event);
    }

    public function testUpdateUserSubmissionUnauthorized(): void
    {
        Event::fake();

        $userSubmission = UserSubmission::factory()->create();
        $this->json('PUT', '/api/admin/user-submissions/' . $userSubmission->getKey(), [])
            ->assertUnauthorized();
    }

    public function testUpdateUserSubmissionStudentUser(): void
    {
        Event::fake();

        $user = $this->makeStudent();
        $userSubmission = UserSubmission::factory()->create();
        $this->actingAs($user, 'api')
            ->json('PUT', '/api/admin/user-submissions/' . $userSubmission->getKey(), [])
            ->assertForbidden();
    }

    public function testDeleteUserSubmission(): void
    {
        $admin = $this->makeAdmin();
        $userSubmission = UserSubmission::factory()->create();
        $this->actingAs($admin, 'api')
            ->json('DELETE', '/api/admin/user-submissions/' . $userSubmission->getKey())
            ->assertOk();
    }

    public function testDeleteUserSubmissionWithEvent(): void
    {
        Event::fake();
        $admin = $this->makeAdmin();
        $productable = ExampleProductable::factory()->create();
        $userSubmission = UserSubmission::factory()->create([
            'email' => 'test@example.com',
            'morphable_id' => $productable->getKey(),
            'morphable_type' => ExampleProductable::class
        ]);

        $this->actingAs($admin, 'api')
            ->json('DELETE', '/api/admin/user-submissions/' . $userSubmission->getKey())
            ->assertOk();

        Event::assertDispatched(
            UnassignProductable::class,
            fn(UnassignProductable $event) => $event->getProductable()->getKey() === $productable->getKey()
                && $event->getUser()->email = 'test@example.com'
        );

        $product = Product::factory()->create();
        $userSubmission = UserSubmission::factory()->create([
            'morphable_id' => $product->getKey(),
            'morphable_type' => Product::class
        ]);

        $this->actingAs($admin, 'api')
            ->json('DELETE', '/api/admin/user-submissions/' . $userSubmission->getKey())
            ->assertOk();

        Event::assertDispatched(
            UnassignProduct::class,
            fn(UnassignProduct $event) => $event->getProduct()->getKey() === $product->getKey()
                && $event->getUser()->email = 'test@example.com'
        );
    }

    public function testDeleteNotExistingUserSubmission(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->json('DELETE', '/api/admin/user-submissions/1')
            ->assertNotFound();
    }

    public function testDeleteUserSubmissionUnauthorizedUser(): void
    {
        $this->json('DELETE', '/api/admin/user-submissions/1')
            ->assertUnauthorized();
    }

    public function testDeleteUserSubmissionStudentUser(): void
    {
        $student = $this->makeStudent();
        $userSubmission = UserSubmission::factory()->create();

        $this->actingAs($student, 'api')
            ->json('DELETE', '/api/admin/user-submissions/' . $userSubmission->getKey())
            ->assertForbidden();
    }

    private function assertApiIndexResponse(TestResponse $response, int $count): void
    {
        $response->assertJsonCount($count, 'data');
        $response->assertJsonStructure([
            'data' => [[
                'email',
                'morphable_id',
                'morphable_type',
                'status',
                'created_at'
            ]]
        ]);
    }
}
