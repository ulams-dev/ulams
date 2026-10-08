<?php

namespace Ulams\Tasks\Tests\Api;

use Illuminate\Support\Carbon;
use Ulams\Tasks\Database\Seeders\TaskPermissionSeeder;
use Ulams\Tasks\Events\TaskAssignedEvent;
use Ulams\Tasks\Tests\CreatesUsers;
use Ulams\Tasks\Tests\TaskTesting;
use Ulams\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

class TaskCreateApiTest extends TestCase
{
    use TaskTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskPermissionSeeder::class);

        Event::fake();
    }

    public function testUserCreateTask(): void
    {
        $user = $this->makeStudent();
        $payload = $this->userCreationPayload();

        $this->actingAs($user, 'api')
            ->postJson('api/tasks', $payload)
            ->assertCreated();

        $this->assertDatabaseHasTask($payload, [
            'user_id' => $user->id,
            'created_by_id' => $user->id,
        ]);

        Event::assertNotDispatched(TaskAssignedEvent::class);
    }

    public function testUserCreateTaskNullableRelated(): void
    {
        $user = $this->makeStudent();
        $payload = $this->userCreationPayload([
            'related_type' => null,
            'related_id' => null,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks', $payload)
            ->assertCreated();

        $this->assertDatabaseHasTask($payload, [
            'user_id' => $user->id,
            'created_by_id' => $user->id,
            'related_type' => null,
            'related_id' => null,
        ]);

        Event::assertNotDispatched(TaskAssignedEvent::class);
    }

    public function testUserCreateTaskRelatedValidation(): void
    {
        $user = $this->makeStudent();

        $this->actingAs($user, 'api')
            ->postJson('api/tasks', $this->userCreationPayload([
                'related_type' => 'Test',
                'related_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['related_id']);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks', $this->userCreationPayload([
                'related_type' => null,
                'related_id' => 123,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['related_type']);

        Event::assertNotDispatched(TaskAssignedEvent::class);
    }

    public function testUserCreateTaskExceptUserId(): void
    {
        $user = $this->makeStudent();
        $payload = $this->userCreationPayload([
            'user_id' => -123
        ]);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks', $payload)
            ->assertCreated();

        $this->assertDatabaseHasTask($payload, [
            'user_id' => $user->id,
            'created_by_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('tasks', [
            'title' => $payload['title'],
            'description' => $payload['description'],
            'user_id' => $payload['user_id'],
            'created_by_id' => $user->id,
            'due_date' => $payload['due_date'],
            'related_type' => $payload['related_type'],
            'related_id' => $payload['related_id'],
        ]);

        Event::assertNotDispatched(TaskAssignedEvent::class);
    }

    #[DataProvider('userInvalidDataProvider')]
    public function testUserCreateTaskInvalidData(string $key, array $data): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/tasks', $this->userCreationPayload($data))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$key]);
    }

    public function testUserCreateTaskUnauthorized(): void
    {
        $this->postJson('api/tasks', $this->userCreationPayload())
            ->assertUnauthorized();
    }

    public function testUserCreateTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('api/tasks')
            ->assertForbidden();
    }

    public function testAdminCreateTask(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->adminCreationPayload();

        $this->actingAs($user, 'api')
            ->postJson('api/admin/tasks', $payload)
            ->assertCreated();

        $this->assertDatabaseHasTask($payload, [
            'created_by_id' => $user->id,
        ]);

        Event::assertDispatched(TaskAssignedEvent::class);
    }

    public function testAdminCreateTaskNullableRelated(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->adminCreationPayload([
            'related_type' => null,
            'related_id' => null,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('api/admin/tasks', $payload)
            ->assertCreated();

        $this->assertDatabaseHasTask($payload, [
            'created_by_id' => $user->id,
            'related_type' => null,
            'related_id' => null,
        ]);

        Event::assertDispatched(TaskAssignedEvent::class);
    }

    public function testAdminCreateTaskRelatedValidation(): void
    {
        $user = $this->makeAdmin();

        $this->actingAs($user, 'api')
            ->postJson('api/admin/tasks', $this->userCreationPayload([
                'related_type' => 'Test',
                'related_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['related_id']);

        $this->actingAs($user, 'api')
            ->postJson('api/admin/tasks', $this->userCreationPayload([
                'related_type' => null,
                'related_id' => 123,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['related_type']);

        Event::assertNotDispatched(TaskAssignedEvent::class);
    }

    #[DataProvider('adminInvalidDataProvider')]
    public function testAdminCreateTaskInvalidData(string $key, array $data): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/tasks', $this->adminCreationPayload($data))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$key]);

        Event::assertNotDispatched(TaskAssignedEvent::class);
    }

    public function testAdminCreateTaskUnauthorized(): void
    {
        $this->postJson('api/admin/tasks', $this->userCreationPayload())
            ->assertUnauthorized();
    }

    public function testAdminCreateTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('api/admin/tasks', $this->userCreationPayload())
            ->assertForbidden();
    }

    public static function userInvalidDataProvider(): array
    {
        return [
            ['key' => 'title', 'data' => ['title' => null]],
            ['key' => 'due_date', 'data' => ['due_date' => Carbon::now()->subDay()]],
            ['key' => 'related_type', 'data' => ['related_type' => 123]],
            ['key' => 'related_id', 'data' => ['related_id' => 'String']],
        ];
    }


    public static function adminInvalidDataProvider(): array
    {
        return [
            ['key' => 'title', 'data' => ['title' => null]],
            ['key' => 'due_date', 'data' => ['due_date' => Carbon::now()->subDay()]],
            ['key' => 'user_id', 'data' => ['user_id' => -123]],
            ['key' => 'user_id', 'data' => ['user_id' => null]],
            ['key' => 'related_type', 'data' => ['related_type' => 123]],
            ['key' => 'related_id', 'data' => ['related_id' => 'String']],
        ];
    }
}
