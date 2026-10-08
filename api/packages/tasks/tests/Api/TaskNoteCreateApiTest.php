<?php

namespace Ulams\Tasks\Tests\Api;

use Ulams\Tasks\Database\Seeders\TaskPermissionSeeder;
use Ulams\Tasks\Events\TaskNoteCreatedEvent;
use Ulams\Tasks\Events\TaskNoteEvent;
use Ulams\Tasks\Models\TaskNote;
use Ulams\Tasks\Tests\CreatesUsers;
use Ulams\Tasks\Tests\TaskTesting;
use Ulams\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class TaskNoteCreateApiTest extends TestCase
{
    use TaskTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskPermissionSeeder::class);

        Event::fake();
    }

    public function testCreateTaskNote(): void
    {
        $user = $this->makeStudent();
        $payload = $this->creationTaskNotePayload([], null, $user);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks/notes', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('task_notes', [
            'note' => $payload['note'],
            'task_id' => $payload['task_id'],
            'user_id' => $user->getKey(),
        ]);

        Event::assertDispatched(TaskNoteCreatedEvent::class);
    }

    public function testCreateTaskNoteNotifyCreatorTask(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->creationTaskNotePayload([], null, $user);

        $response = $this->actingAs($user, 'api')
            ->postJson('api/tasks/notes', $payload)
            ->assertCreated();

        $taskNote = TaskNote::find($response->getData()->data->id);

        Event::assertDispatched(TaskNoteCreatedEvent::class, function (TaskNoteEvent $event) use ($taskNote) {
            return $taskNote->task->user_id === $event->getUser()->getKey() && $taskNote->getKey() === $event->getTaskNote()->getKey();
        });
    }

    public function testCreateTaskNoteNotifyAssignedUser(): void
    {
        $user = $this->makeStudent();
        $payload = $this->creationTaskNotePayload([], $user, null);

        $response = $this->actingAs($user, 'api')
            ->postJson('api/tasks/notes', $payload)
            ->assertCreated();

        $taskNote = TaskNote::find($response->getData()->data->id);

        Event::assertDispatched(TaskNoteCreatedEvent::class, function (TaskNoteEvent $event) use ($taskNote) {
            return $taskNote->task->created_by_id === $event->getUser()->getKey() && $taskNote->getKey() === $event->getTaskNote()->getKey();
        });
    }

    public function testCreateTaskNoteUserIsOwner(): void
    {
        $user = $this->makeStudent();
        $payload = $this->creationTaskNotePayload([], $user);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks/notes', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('task_notes', [
            'note' => $payload['note'],
            'task_id' => $payload['task_id'],
            'user_id' => $user->getKey(),
        ]);
    }

    public function testCreateTaskNoteInvalidData(): void
    {
        $user = $this->makeStudent();
        $payload = $this->creationTaskNotePayload(['note' => null], $user);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks/notes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    }

    public function testCreateTaskNoteTaskUserNotOwner(): void
    {
        $user = $this->makeStudent();
        $payload = $this->creationTaskNotePayload();

        $this->actingAs($user, 'api')
            ->postJson('api/tasks/notes', $payload)
            ->assertForbidden();
    }

    public function testCreateTaskNoteTaskUserNotAssigned(): void
    {
        $user = $this->makeStudent();
        $payload = $this->creationTaskNotePayload();

        $this->actingAs($user, 'api')
            ->postJson('api/tasks/notes', $payload)
            ->assertForbidden();
    }

    public function testCreateTaskNoteTaskNotFound(): void
    {
        $user = $this->makeStudent();
        $payload = $this->creationTaskNotePayload(['task_id' => -123]);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks/notes', $payload)
            ->assertNotFound();
    }

    public function testCreateTaskNoteForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('api/tasks/notes', $this->creationTaskNotePayload())
            ->assertForbidden();
    }

    public function testCreateTaskNoteUnauthorized(): void
    {
        $this->postJson('api/tasks/notes', $this->creationTaskNotePayload())
            ->assertUnauthorized();
    }

    public function testAdminCreateTaskNote(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->creationTaskNotePayload();

        $this->actingAs($user, 'api')
            ->postJson('api/admin/tasks/notes', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('task_notes', [
            'note' => $payload['note'],
            'task_id' => $payload['task_id'],
            'user_id' => $user->getKey(),
        ]);

        Event::assertDispatched(TaskNoteCreatedEvent::class);
    }

    public function testAdminCreateTaskNoteNotifyCreatorTask(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->creationTaskNotePayload();

        $response = $this->actingAs($user, 'api')
            ->postJson('api/admin/tasks/notes', $payload)
            ->assertCreated();

        $taskNote = TaskNote::find($response->getData()->data->id);

        Event::assertDispatched(TaskNoteCreatedEvent::class, function (TaskNoteEvent $event) use ($taskNote) {
            return $taskNote->task->user_id === $event->getUser()->getKey() && $taskNote->getKey() === $event->getTaskNote()->getKey();
        });
    }

    public function testAdminCreateTaskNoteNotifyAssignedUser(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->creationTaskNotePayload([], $user);

        $response = $this->actingAs($user, 'api')
            ->postJson('api/admin/tasks/notes', $payload)
            ->assertCreated();

        $taskNote = TaskNote::find($response->getData()->data->id);

        Event::assertDispatched(TaskNoteCreatedEvent::class, function (TaskNoteEvent $event) use ($taskNote) {
            return $taskNote->task->created_by_id === $event->getUser()->getKey() && $taskNote->getKey() === $event->getTaskNote()->getKey();
        });
    }

    public function testAdminCreateTaskNoteTaskNotFound(): void
    {
        $user = $this->makeAdmin();
        $payload = $this->creationTaskNotePayload(['task_id' => -123]);

        $this->actingAs($user, 'api')
            ->postJson('api/admin/tasks/notes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['task_id']);
    }

    public function testAdminCreateTaskNoteForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('api/admin/tasks/notes', $this->creationTaskNotePayload())
            ->assertForbidden();
    }

    public function testAdminCreateTaskNoteUnauthorized(): void
    {
        $this->postJson('api/admin/tasks/notes', $this->creationTaskNotePayload())
            ->assertUnauthorized();
    }
}
