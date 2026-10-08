<?php

namespace EscolaLms\Notifications\Tests\Feature;

use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Notifications\Core\EventNotification;
use EscolaLms\Notifications\Models\DatabaseNotification;
use EscolaLms\Notifications\Models\User as ModelsUser;
use EscolaLms\Notifications\Tests\Mocks\TestEvent;
use EscolaLms\Notifications\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class NotificationTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    public User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeStudent();
        $this->friend = $this->makeStudent();
    }

    public function test_dispatching_fake_event()
    {
        Event::fake();

        event(new TestEvent($this->user, $this->friend, 'foo'));

        Event::assertDispatched(TestEvent::class);
    }

    public function test_dispatching_fake_notification()
    {
        Notification::fake();

        event(new TestEvent($this->user, $this->friend, 'foo'));

        Notification::assertSentTo($this->user, EventNotification::class);
    }

    public function test_dispatching_notification()
    {
        event(new TestEvent($this->user, $this->friend, 'foo'));

        $this->user->refresh();

        $database_notification = $this->user->notifications()->where('event', TestEvent::class)->first();

        if (!is_a($database_notification, DatabaseNotification::class)) {
            $database_notification = DatabaseNotification::find($database_notification->getKey());
        }

        $this->assertEquals($this->friend->getKey(), $database_notification->data['friend']->getKey());
        $this->assertEquals('foo', $database_notification->data['string']);
    }

    public function test_should_not_save_database_notification()
    {
        $user = new User();
        $user->email = 'test@test.com';

        event(new TestEvent($user, $this->friend, 'foo'));

        $this->assertDatabaseMissing('notifications', [
            'event' => TestEvent::class
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }
}
