<?php

namespace Ulams\Tasks\Tests\Api;

use Ulams\Tasks\Events\TaskIncompleteEvent;
use Illuminate\Support\Carbon;
use Ulams\Tasks\Database\Seeders\TaskPermissionSeeder;
use Ulams\Tasks\Events\TaskCompleteRequestEvent;
use Ulams\Tasks\Models\Task;
use Ulams\Tasks\Tests\CreatesUsers;
use Ulams\Tasks\Tests\TaskTesting;
use Ulams\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class TaskCompletionApiTest extends TestCase
{
    use TaskTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskPermissionSeeder::class);

        Event::fake();
    }

    public function testUserCompleteTask(): void
    {
        $user = $this->makeStudent();
        $task = Task::factory()->create([
            'user_id' => $user->getKey(),
            'created_by_id' => $user->getKey(),
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson('api/tasks/complete/' . $task->getKey())
            ->assertOk();

        $completedAt = Carbon::make($response->getData()->data->completed_at)->toDateTimeString();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'user_id' => $user->getKey(),
            'created_by_id' => $user->getKey(),
            'completed_at' => $completedAt,
        ]);
    }

    public function testUserIncompleteTask(): void
    {
        $user = $this->makeStudent();
        $task = Task::factory()->create([
            'user_id' => $user->getKey(),
            'created_by_id' => $user->getKey(),
            'completed_at' => Carbon::now()
        ]);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks/incomplete/' . $task->getKey())
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'user_id' => $user->getKey(),
            'created_by_id' => $user->getKey(),
            'completed_at' => null,
        ]);

        Event::assertNotDispatched(TaskCompleteRequestEvent::class);
    }

    public function testUserCompleteTaskAssigned(): void
    {
        $user = $this->makeStudent();
        $task = Task::factory()->create([
            'user_id' => $user->getKey(),
        ]);

        $this->actingAs($user, 'api')
            ->postJson('api/tasks/complete/' . $task->getKey())
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'user_id' => $user->getKey(),
            'completed_at' => null,
        ]);

        Event::assertDispatched(TaskCompleteRequestEvent::class, function (TaskCompleteRequestEvent $event) use ($task) {
            return $event->getUser()->getKey() === $task->created_by_id && $event->getTask()->getKey() === $task->getKey();
        });
    }

    public function testUserCompleteTaskNotAssigned(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/tasks/complete/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testUserIncompleteTaskNotAssigned(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/tasks/incomplete/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }


    public function testUserCompleteTaskUnauthorized(): void
    {
        $this->postJson('api/tasks/complete/' . Task::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function testUserCompleteTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('api/tasks/complete/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testUserIncompleteTaskUnauthorized(): void
    {
        $this->postJson('api/tasks/incomplete/' . Task::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function testUserIncompleteTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('api/tasks/incomplete/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testAdminCompleteTask(): void
    {
        $task = Task::factory()->create();

        $response = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/tasks/complete/' . $task->getKey())
            ->assertOk();

        $completedAt = Carbon::make($response->getData()->data->completed_at)->toDateTimeString();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'completed_at' => $completedAt,
        ]);
    }

    public function testAdminIncompleteTask(): void
    {
        Event::fake([TaskIncompleteEvent::class]);

        $task = Task::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/tasks/incomplete/' . $task->getKey())
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'completed_at' => null,
        ]);

        Event::assertDispatched(function (TaskIncompleteEvent $event) use ($task) {
            return $event->getUser()->getKey() === $task->user_id && $event->getTask()->getKey() === $task->getKey();
        });
    }

    public function testAdminCompleteTaskUnauthorized(): void
    {
        $this->postJson('api/admin/tasks/complete/' . Task::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function testAdminCompleteTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('api/admin/tasks/complete/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testAdminIncompleteTaskUnauthorized(): void
    {
        $this->postJson('api/admin/tasks/incomplete/' . Task::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function testAdminIncompleteTaskForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('api/admin/tasks/incomplete/' . Task::factory()->create()->getKey())
            ->assertForbidden();
    }
}
