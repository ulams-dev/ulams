<?php

namespace EscolaLms\Tasks\Tests\Api;

use EscolaLms\Tasks\Database\Seeders\TaskPermissionSeeder;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Models\TaskNote;
use EscolaLms\Tasks\Tests\CreatesUsers;
use EscolaLms\Tasks\Tests\TaskTesting;
use EscolaLms\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class TaskNoteDeleteApiTest extends TestCase
{
    use TaskTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskPermissionSeeder::class);

        Event::fake();
    }

    public function testDeleteTaskNote(): void
    {
        $user = $this->makeStudent();
        $task = Task::factory()->create(['user_id' => $user->getKey()]);
        $taskNote = TaskNote::factory()
            ->state(['user_id' => $user->getKey(), 'task_id' => $task->getKey()])
            ->create();

        $this->actingAs($user, 'api')
            ->deleteJson('api/tasks/notes/' . $taskNote->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('task_notes', [
            'id' => $taskNote->getKey(),
        ]);
    }

    public function testDeleteTaskNoteNotOwner(): void
    {
        $user = $this->makeStudent();
        $taskNote = TaskNote::factory()->create();

        $this->actingAs($user, 'api')
            ->deleteJson('api/tasks/notes/' . $taskNote->getKey())
            ->assertForbidden();
    }

    public function testUpdateTaskNoteNotFound(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->deleteJson('api/tasks/notes/' . 123)
            ->assertNotFound();
    }

    public function testUpdateTaskNoteForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->deleteJson('api/tasks/notes/' . TaskNote::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testUpdateTaskNoteUnauthorized(): void
    {
        $this->deleteJson('api/tasks/notes/' . TaskNote::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function testAdminDeleteTaskNote(): void
    {
        $user = $this->makeAdmin();
        $taskNote = TaskNote::factory()->create();

        $this->actingAs($user, 'api')
            ->deleteJson('api/admin/tasks/notes/' . $taskNote->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('task_notes', [
            'id' => $taskNote->getKey(),
        ]);
    }

    public function testAdminUpdateTaskNoteNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('api/admin/tasks/notes/' . 123)
            ->assertNotFound();
    }

    public function testAdminUpdateTaskNoteForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->deleteJson('api/admin/tasks/notes/' . TaskNote::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testAdminUpdateTaskNoteUnauthorized(): void
    {
        $this->deleteJson('api/admin/tasks/notes/' . TaskNote::factory()->create()->getKey())
            ->assertUnauthorized();
    }
}
