<?php

namespace EscolaLms\Tasks\Tests\Api;

use EscolaLms\Tasks\Database\Seeders\TaskPermissionSeeder;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Models\TaskNote;
use EscolaLms\Tasks\Tests\CreatesUsers;
use EscolaLms\Tasks\Tests\TaskTesting;
use EscolaLms\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class TaskNoteUpdateApiTest extends TestCase
{
    use TaskTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskPermissionSeeder::class);

        Event::fake();
    }

    public function testUpdateTaskNote(): void
    {
        $user = $this->makeStudent();
        $task = Task::factory()->create(['user_id' => $user->getKey()]);
        $taskNote = TaskNote::factory()
            ->state(['user_id' => $user->getKey(), 'task_id' => $task->getKey()])
            ->create();
        $payload = $this->updateTaskNotePayload(['task_id' => $task->getKey()]);

        $this->actingAs($user, 'api')
            ->patchJson('api/tasks/notes/' . $taskNote->getKey(), $payload)
            ->assertOk();

        $this->assertDatabaseHas('task_notes', [
            'id' => $taskNote->getKey(),
            'note' => $payload['note'],
            'task_id' => $taskNote->task_id,
            'user_id' => $user->getKey(),
        ]);
    }

    public function testUpdateTaskNoteInvalidData(): void
    {
        $user = $this->makeStudent();
        $task = Task::factory()->create(['user_id' => $user->getKey()]);
        $taskNote = TaskNote::factory()
            ->state(['user_id' => $user->getKey(), 'task_id' => $task->getKey()])
            ->create();
        $payload = $this->updateTaskNotePayload(['note' => null, 'task_id' => $task->getKey()]);

        $this->actingAs($user, 'api')
            ->patchJson('api/tasks/notes/' . $taskNote->getKey(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    }

    public function testUpdateTaskNoteNotOwner(): void
    {
        $user = $this->makeStudent();
        $taskNote = TaskNote::factory()->create();
        $task = $taskNote->task;
        $payload = $this->updateTaskNotePayload(['task_id' => $task->getKey()]);

        $this->actingAs($user, 'api')
            ->patchJson('api/tasks/notes/' . $taskNote->getKey(), $payload)
            ->assertForbidden();
    }

    public function testUpdateTaskNoteNotFound(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/tasks/notes/' . 123, $this->updateTaskNotePayload())
            ->assertNotFound();
    }

    public function testUpdateTaskNoteForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->patchJson('api/tasks/notes/' . TaskNote::factory()->create()->getKey(), $this->updateTaskNotePayload())
            ->assertForbidden();
    }

    public function testUpdateTaskNoteUnauthorized(): void
    {
        $this->patchJson('api/tasks/notes/' . TaskNote::factory()->create()->getKey(), $this->updateTaskNotePayload())
            ->assertUnauthorized();
    }

    public function testAdminUpdateTaskNote(): void
    {
        $user = $this->makeAdmin();
        $taskNote = TaskNote::factory()->create();
        $payload = $this->updateTaskNotePayload(['task_id' => $taskNote->task->getKey()]);

        $this->actingAs($user, 'api')
            ->patchJson('api/admin/tasks/notes/' . $taskNote->getKey(), $payload)
            ->assertOk();

        $this->assertDatabaseHas('task_notes', [
            'id' => $taskNote->getKey(),
            'note' => $payload['note'],
            'task_id' => $taskNote->task_id,
            'user_id' => $user->getKey(),
        ]);
    }

    public function testAdminUpdateTaskNoteInvalidData(): void
    {
        $user = $this->makeAdmin();
        $taskNote = TaskNote::factory()->create();
        $payload = $this->updateTaskNotePayload(['note' => null, 'task_id' => $taskNote->task->getKey()]);

        $this->actingAs($user, 'api')
            ->patchJson('api/admin/tasks/notes/' . $taskNote->getKey(), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    }

    public function testAdminUpdateTaskNoteNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/tasks/notes/' . 123, $this->updateTaskNotePayload())
            ->assertNotFound();
    }

    public function testAdminUpdateTaskNoteForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->patchJson('api/admin/tasks/notes/' . TaskNote::factory()->create()->getKey(), $this->updateTaskNotePayload())
            ->assertForbidden();
    }

    public function testAdminUpdateTaskNoteUnauthorized(): void
    {
        $this->patchJson('api/admin/tasks/notes/' . TaskNote::factory()->create()->getKey(), $this->updateTaskNotePayload())
            ->assertUnauthorized();
    }
}
