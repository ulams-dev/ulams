<?php

namespace Ulams\TemplatesEmail\Tests\Feature;

use Ulams\Tasks\Events\TaskOverdueEvent;
use Ulams\Tasks\Models\Task;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class TaskTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\Ulams\Tasks\UlamsTasksServiceProvider::class)) {
            $this->markTestSkipped('Task package not installed');
        }
    }

    public function testNotificationSentWhenTaskIsOverdue(): void
    {
        Mail::fake();

        $student = $this->makeStudent();

        /** @var Task $task */
        $task = Task::factory()
            ->state([
                'user_id' => $student->getKey(),
                'due_date' => Carbon::now()->subDays(4),
            ])
            ->create();

        Event::dispatch(new TaskOverdueEvent($task->user, $task));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student, $task) {
            $this->assertEquals(__('Task ":task" is overdue', ['task' => $task->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($student->email));
            return true;
        });
    }
}
