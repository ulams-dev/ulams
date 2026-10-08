<?php

namespace EscolaLms\TemplatesEmail\Tests\Api;

use EscolaLms\Tasks\Database\Seeders\TaskPermissionSeeder;
use EscolaLms\Tasks\Events\TaskAssignedEvent;
use EscolaLms\Tasks\Events\TaskCompleteRequestEvent;
use EscolaLms\Tasks\Events\TaskCompleteUserConfirmationEvent;
use EscolaLms\Tasks\Events\TaskIncompleteEvent;
use EscolaLms\Tasks\Events\TaskNoteCreatedEvent;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Models\TaskNote;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Templates\Listeners\TemplateEventListener;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class TaskTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions, WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Tasks\EscolaLmsTasksServiceProvider::class)) {
            $this->markTestSkipped('Task package not installed');
        }

        $this->seed(TaskPermissionSeeder::class);
        Notification::fake();
        Mail::fake();
    }

    public function testNotificationOnTaskAssignment(): void
    {
        Event::fake([TaskAssignedEvent::class]);

        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin, 'api')
            ->postJson('api/admin/tasks', [
                'title' => $this->faker->title,
                'user_id' => $student->getKey(),
            ])
            ->assertCreated();

        Event::assertDispatched(function (TaskAssignedEvent $event) use ($student) {
            $this->assertEquals($student->getKey(), $event->getUser()->getKey());
            return true;
        });

        /** @var Task $task */
        $task = Task::latest()->first();
        $listener = app(TemplateEventListener::class);
        $listener->handle(new TaskAssignedEvent($task->user, $task));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student, $task) {
            $this->assertEquals(__('Task ":task" assigned', ['task' => $task->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($student->email));
            return true;
        });
    }

    public function testNotificationOnTaskCompletedByAdmin(): void
    {
        Event::fake([TaskCompleteUserConfirmationEvent::class]);

        $student = $this->makeStudent();

        /** @var Task $task */
        $task = Task::factory()
            ->state(['user_id' => $student->getKey()])
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/tasks/complete/' . $task->getKey())
            ->assertOk();

        Event::assertDispatched(function (TaskCompleteUserConfirmationEvent $event) use ($student) {
            $this->assertEquals($student->getKey(), $event->getUser()->getKey());
            return true;
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new TaskCompleteUserConfirmationEvent($task->user, $task));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student, $task) {
            $this->assertEquals(__('Task ":task" completed', ['task' => $task->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($student->email));
            return true;
        });
    }

    public function testNotificationSentToCreatorWhenStudentCompletesTask(): void
    {
        Event::fake([TaskCompleteRequestEvent::class]);

        $student = $this->makeStudent();
        $admin = $this->makeAdmin();

        /** @var Task $task */
        $task = Task::factory()
            ->state([
                'created_by_id' => $admin->getKey(),
                'user_id' => $student->getKey(),
            ])
            ->create();

        $this->actingAs($student, 'api')
            ->postJson('api/tasks/complete/' . $task->getKey())
            ->assertOk();

        Event::assertDispatched(function (TaskCompleteRequestEvent $event) use ($admin) {
            $this->assertEquals($admin->getKey(), $event->getTask()->created_by_id);
            return true;
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new TaskCompleteRequestEvent($task->createdBy, $task));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student, $admin) {
            $this->assertEquals(__(':assignee_name has completed the task', ['assignee_name' => $student->name]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($admin->email));
            return true;
        });
    }

    public function testNotificationOnTaskMarkedIncomplete(): void
    {
        Event::fake([TaskIncompleteEvent::class]);

        $student = $this->makeStudent();

        /** @var Task $task */
        $task = Task::factory()
            ->state(['user_id' => $student->getKey()])
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/tasks/incomplete/' . $task->getKey())
            ->assertOk();

        Event::assertDispatched(function (TaskIncompleteEvent $event) use ($task) {
            return $event->getUser()->getKey() === $task->user_id && $event->getTask()->getKey() === $task->getKey();
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new TaskIncompleteEvent($task->user, $task));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student, $task) {
            $this->assertEquals(__('Incomplete task ":task"', ['task' => $task->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($student->email));
            return true;
        });
    }

    public function testNotificationOnTaskNoteCreated(): void
    {
        Event::fake([TaskNoteCreatedEvent::class]);

        $student = $this->makeStudent();
        $admin = $this->makeAdmin();

        /** @var Task $task */
        $task = Task::factory()
            ->state([
                'user_id' => $student->getKey(),
                'created_by_id' => $admin->getKey(),
            ])
            ->create();

        $this->actingAs($admin, 'api')
            ->postJson('api/admin/tasks/notes', [
                'note' => $this->faker->text,
                'task_id' => $task->getKey(),
            ])
            ->assertCreated();

        Event::assertDispatched(function (TaskNoteCreatedEvent $event) use ($task, $student) {
            $this->assertEquals($student->getKey(), $event->getUser()->getKey());
            $this->assertEquals($task->getKey(), $event->getTaskNote()->task_id);
            return true;
        });

        /** @var TaskNote $task */
        $note = TaskNote::latest()->first();

        $listener = app(TemplateEventListener::class);
        $listener->handle(new TaskNoteCreatedEvent($task->user, $note));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student, $task) {
            $this->assertEquals(__('New note for the ":task" task', ['task' => $task->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($student->email));
            return true;
        });
    }
}
