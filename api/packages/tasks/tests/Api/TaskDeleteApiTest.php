<?php

namespace EscolaLms\Tasks\Tests\Api;

use EscolaLms\Tasks\Database\Seeders\TaskPermissionSeeder;
use EscolaLms\Tasks\Events\TaskDeletedEvent;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Models\TaskNote;
use EscolaLms\Tasks\Tests\CreatesUsers;
use EscolaLms\Tasks\Tests\TaskTesting;
use EscolaLms\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class TaskDeleteApiTest extends TestCase
{
    use TaskTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskPermissionSeeder::class);

        Event::fake();
    }

    public function testUserDeleteTask(): void
    {
        $user = $this->makeStudent();

        $task = Task::factory()->create(['user_id' => $user->getKey(), 'created_by_id' => $user->getKey()]);

        $this->actingAs($user, 'api')
            ->deleteJson('api/tasks/' . $task->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->getKey()
        ]);

        Event::assertNotDispatched(TaskDeletedEvent::class);
    }

    public function testUserDeleteTaskWithNotes(): void
    {
        $user = $this->makeStudent();

        $task = Task::factory()
            ->has(TaskNote::factory()->state(['user_id' => $user->getKey()])->count(5))
            ->create(['user_id' => $user->getKey(), 'created_by_id' => $user->getKey()]);

        $this->actingAs($user, 'api')
            ->deleteJson('api/tasks/' . $task->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->getKey()
        ]);
        $this->assertDatabaseMissing('task_notes', [
            'task_id' => $task->getKey()
        ]);

        Event::assertNotDispatched(TaskDeletedEvent::class);
    }

    public function testUserDeleteTaskNotExists(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->deleteJson('api/tasks/' . 123)
            ->assertNotFound();
    }

    public function testUserDeleteTaskNotOwner(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->deleteJson('api/tasks/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testUserDeleteTaskUnauthorized(): void
    {
        $this->deleteJson('api/tasks/' . Task::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function testUserDeleteTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->deleteJson('api/tasks/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testAdminDeleteTask(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('api/admin/tasks/' . Task::factory()->create()->getKey())
            ->assertOk();

        Event::assertDispatched(TaskDeletedEvent::class);
    }

    public function testAdminDeleteTaskWithNotes(): void
    {
        $user = $this->makeAdmin();

        $task = Task::factory()
            ->has(TaskNote::factory()->count(5))
            ->create();

        $this->actingAs($user, 'api')
            ->deleteJson('api/admin/tasks/' . $task->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->getKey()
        ]);
        $this->assertDatabaseMissing('task_notes', [
            'task_id' => $task->getKey()
        ]);

        Event::assertDispatched(TaskDeletedEvent::class);
    }

    public function testAdminDeleteTaskNotExists(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('api/admin/tasks/' . 123)
            ->assertNotFound();

        Event::assertNotDispatched(TaskDeletedEvent::class);
    }

    public function testAdminDeleteTaskUnauthorized(): void
    {
        $this->deleteJson('api/admin/tasks/' . Task::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function testAdminDeleteTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->deleteJson('api/admin/tasks/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }
}
