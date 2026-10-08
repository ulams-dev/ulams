<?php

namespace EscolaLms\Tasks\Tests\Api;

use EscolaLms\Tasks\Database\Seeders\TaskPermissionSeeder;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Tests\CreatesUsers;
use EscolaLms\Tasks\Tests\TaskTesting;
use EscolaLms\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class TaskUpdateApiTest extends TestCase
{
    use TaskTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskPermissionSeeder::class);

        Event::fake();
    }

    public function testUserUpdateTask(): void
    {
        $user = $this->makeStudent();
        $payload = $this->userCreationPayload();
        $task = Task::factory()->create([
            'user_id' => $user->getKey(),
            'created_by_id' => $user->getKey(),
        ]);

        $this->actingAs($user, 'api')
            ->patchJson('api/tasks/' . $task->getKey(), $payload)
            ->assertOk();

        $this->assertDatabaseHasTask($payload, ['user_id' => $user->id, 'created_by_id' => $user->id,]);
    }

    public function testUserUpdateTaskNullableRelated(): void
    {
        $user = $this->makeStudent();
        $payload = $this->userCreationPayload([
            'related_type' => null,
            'related_id' => null,
        ]);
        $task = Task::factory()->create([
            'user_id' => $user->getKey(),
            'created_by_id' => $user->getKey(),
        ]);

        $this->actingAs($user, 'api')
            ->patchJson('api/tasks/' . $task->getKey(), $payload)
            ->assertOk();

        $this->assertDatabaseHasTask($payload, [
            'user_id' => $user->id,
            'created_by_id' => $user->id,
            'related_type' => null,
            'related_id' => null,
        ]);
    }

    public function testUserUpdateTaskRelatedValidation(): void
    {
        $user = $this->makeStudent();
        $task = Task::factory()->create([
            'user_id' => $user->getKey(),
            'created_by_id' => $user->getKey(),
        ]);

        $this->actingAs($user, 'api')
            ->patchJson('api/tasks/' . $task->getKey(), $this->userCreationPayload([
                'related_type' => 'Test',
                'related_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['related_id']);

        $this->actingAs($user, 'api')
            ->patchJson('api/tasks/' . $task->getKey(), $this->userCreationPayload([
                'related_type' => null,
                'related_id' => 123,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['related_type']);
    }

    public function testUserUpdateTaskExceptUserId(): void
    {
        $user = $this->makeStudent();
        $task = Task::factory()->create([
            'user_id' => $user->getKey(),
            'created_by_id' => $user->getKey(),
        ]);
        $payload = $this->userCreationPayload([
            'user_id' => -123,
        ]);

        $this->actingAs($user, 'api')
            ->patchJson('api/tasks/' . $task->getKey(), $payload)
            ->assertOk();

        $this->assertDatabaseHasTask($payload, ['user_id' => $user->id, 'created_by_id' => $user->id,]);

        $this->assertDatabaseMissing('tasks', [
            'title' => $payload['title'],
            'description' => $payload['description'],
            'type' => $payload['type'],
            'user_id' => $payload['user_id'],
            'created_by_id' => $user->id,
            'due_date' => $payload['due_date'],
            'related_type' => $payload['related_type'],
            'related_id' => $payload['related_id'],
        ]);
    }

    public function testUserUpdateTaskNotExists(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/tasks/' . 123, $this->userCreationPayload())
            ->assertNotFound();
    }

    public function testUserUpdateTaskNotOwner(): void
    {
        $task = Task::factory()->create();

        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/tasks/' . $task->getKey(), $this->userCreationPayload())
            ->assertForbidden();
    }

    public function testUpdateTaskUnauthorized(): void
    {
        $this->patchJson('api/tasks/' . Task::factory()->create()->getKey(), $this->userCreationPayload())
            ->assertUnauthorized();
    }

    public function testUpdateTaskForbidden(): void
    {
        $this
            ->actingAs($this->makeUser(), 'api')
            ->patchJson('api/tasks/' . Task::factory()->create()->getKey(), $this->userCreationPayload())
            ->assertForbidden();
    }

    public function testAdminUpdateTask(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->adminCreationPayload();
        $task = Task::factory()->create(['created_by_id' => $user->getKey()]);

        $this->actingAs($user, 'api')
            ->patchJson('api/admin/tasks/' . $task->getKey(), $payload)
            ->assertOk();

        $this->assertDatabaseHasTask($payload, ['created_by_id' => $user->id,]);
    }

    public function testAdminUpdateTaskNullableRelated(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->adminCreationPayload([
            'related_type' => null,
            'related_id' => null,
        ]);
        $task = Task::factory()->create(['created_by_id' => $user->getKey()]);

        $this->actingAs($user, 'api')
            ->patchJson('api/admin/tasks/' . $task->getKey(), $payload)
            ->assertOk();

        $this->assertDatabaseHasTask($payload, [
            'created_by_id' => $user->id,
            'related_type' => null,
            'related_id' => null,
        ]);
    }

    public function testAdminUpdateTaskRelatedValidation(): void
    {
        $user = $this->makeAdmin();
        $task = Task::factory()->create();

        $this->actingAs($user, 'api')
            ->patchJson('api/admin/tasks/' . $task->getKey(), $this->userCreationPayload([
                'related_type' => 'Test',
                'related_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['related_id']);

        $this->actingAs($user, 'api')
            ->patchJson('api/admin/tasks/' . $task->getKey(), $this->userCreationPayload([
                'related_type' => null,
                'related_id' => 123,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['related_type']);
    }

    public function testAdminUpdateTaskNotExists(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/tasks/' . 123, $this->adminCreationPayload())
            ->assertNotFound();
    }

    public function testAdminUpdateTaskUnauthorized(): void
    {
        $this->patchJson('api/admin/tasks/' . Task::factory()->create()->getKey(), $this->adminCreationPayload())
            ->assertUnauthorized();
    }

    public function testAdminUpdateTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->patchJson('api/admin/tasks/' . Task::factory()->create()->getKey(), $this->adminCreationPayload())
            ->assertForbidden();
    }

}
