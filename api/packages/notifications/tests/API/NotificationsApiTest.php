<?php

namespace EscolaLms\Notifications\Tests\API;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Notifications\Database\Seeders\NotificationsPermissionsSeeder;
use EscolaLms\Notifications\Models\DatabaseNotification;
use EscolaLms\Core\Models\User;
use EscolaLms\Notifications\Tests\Mocks\DifferentTestEvent;
use EscolaLms\Notifications\Tests\Mocks\TestEvent;
use EscolaLms\Notifications\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;

class NotificationsApiTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NotificationsPermissionsSeeder::class);
    }

    public function test_user_can_see_his_notifications()
    {
        $student = $this->makeStudent();
        $friend = $this->makeStudent();

        event(new TestEvent($student, $friend, 'foo'));
        event(new TestEvent($friend, $student, 'foo'));

        $response = $this->actingAs($student)->json('GET', '/api/notifications/');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $json = $response->json();
        $notificationDb = $json['data'][0];

        $this->assertEquals($friend->getKey(), $notificationDb['data']['friend']['id']);
    }

    public function test_user_can_mark_his_notification_as_read()
    {
        $student = $this->makeStudent();
        $friend = $this->makeStudent();

        event(new TestEvent($student, $friend, 'foo'));
        event(new TestEvent($friend, $student, 'foo'));

        $notification = $student->notifications()->latest()->first();
        $friendNotification = $friend->notifications()->latest()->first();

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($student)->json('GET', '/api/notifications/');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->actingAs($student)->json('POST', '/api/notifications/' . $notification->getKey() . '/read');
        $response->assertOk();

        $notification->refresh();
        $this->assertNotNull($notification->read_at);

        $response = $this->actingAs($student)->json('GET', '/api/notifications/');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        $response = $this->actingAs($student)->json('GET', '/api/notifications/', ['include_read' => true]);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->actingAs($student)->json('POST', '/api/notifications/' . $friendNotification->getKey() . '/read');
        $response->assertForbidden();
    }

    public function test_user_can_mark_all_his_notification_as_read(): void
    {
        $student = $this->makeStudent();
        $this->generateEvents($student);

        $this->actingAs($student, 'api')
            ->postJson('api/notifications/read-all')
            ->assertOk();

        $student->notifications->each(fn ($notification) => $this->assertTrue($notification->read()));
    }

    public function test_user_can_not_see_others_notifications()
    {
        $student = $this->makeStudent();
        $friend = $this->makeStudent();

        event(new TestEvent($student, $friend, 'foo'));
        event(new TestEvent($friend, $student, 'foo'));

        $response = $this->actingAs($student)->json('GET', '/api/notifications/' . $friend->getKey());
        $response->assertStatus(404);

        $response = $this->actingAs($student)->json('GET', '/api/admin/notifications/' . $student->getKey());
        $response->assertStatus(401);

        $response = $this->actingAs($student)->json('GET', '/api/admin/notifications/' . $friend->getKey());
        $response->assertStatus(401);
    }

    public function test_admin_can_see_any_notifications()
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $friend = $this->makeStudent();

        event(new TestEvent($student, $friend, 'foo'));
        event(new TestEvent($friend, $student, 'foo'));

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/' . $student->getKey());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $json = $response->json();
        $notificationDb = $json['data'][0];
        $this->assertEquals($friend->getKey(), $notificationDb['data']['friend']['id']);

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/' . $friend->getKey());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $json = $response->json();
        $notificationDb = $json['data'][0];
        $this->assertEquals($student->getKey(), $notificationDb['data']['friend']['id']);
    }

    public function test_admin_can_see_event_list()
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $friend = $this->makeStudent();

        event(new TestEvent($student, $friend, 'foo'));
        event(new TestEvent($friend, $student, 'foo'));
        event(new DifferentTestEvent($student, 'bar'));

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/events');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'data' => [
                TestEvent::class,
                DifferentTestEvent::class,
            ]
        ]);
    }

    public function test_admin_notification_list_with_sorts()
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $friend = $this->makeStudent();

        event(new DifferentTestEvent($student, 'bar'));
        event(new TestEvent($friend, $student, 'foo'));


        $notificationOne = DatabaseNotification::where('notifiable_id', $student->getKey())->first();
        $notificationOne->update(['created_at' => now()->subDay()]);
        $notificationTwo = DatabaseNotification::where('notifiable_id', $friend->getKey())->first();
        $notificationTwo->update(['created_at' => now()->addDay()]);

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/all', [
            'order_by' => 'notifiable_id',
            'order' => 'ASC',
        ]);
        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $notificationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $notificationTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/all', [
            'order_by' => 'notifiable_id',
            'order' => 'DESC',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.0.id') === $notificationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $notificationOne->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/all', [
            'order_by' => 'event',
            'order' => 'ASC',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.0.id') === $notificationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $notificationTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/all', [
            'order_by' => 'event',
            'order' => 'DESC',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.0.id') === $notificationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $notificationOne->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/all', [
            'order_by' => 'created_at',
            'order' => 'ASC',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.0.id') === $notificationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $notificationTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/all', [
            'order_by' => 'created_at',
            'order' => 'DESC',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.0.id') === $notificationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $notificationOne->getKey());

    }

    public function test_admin_can_filter_notifications_by_event()
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $friend = $this->makeStudent();

        event(new TestEvent($student, $friend, 'foo'));
        event(new TestEvent($friend, $student, 'foo'));
        event(new DifferentTestEvent($student, 'bar'));

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/all', [
            'event' => TestEvent::class,
        ]);
        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/all', [
            'event' => DifferentTestEvent::class,
        ]);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/' . $student->getKey(), [
            'event' => DifferentTestEvent::class,
        ]);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/' . $student->getKey(), [
            'event' => TestEvent::class,
        ]);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/' . $friend->getKey(), [
            'event' => DifferentTestEvent::class,
        ]);
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/notifications/' . $friend->getKey(), [
            'event' => TestEvent::class,
        ]);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->actingAs($admin)->json('GET', '/api/admin/notifications/all', [
            'date_from' => Carbon::now()->addDay()->toIso8601String(),
        ]);
        $response->assertOk();

        $response->assertJsonCount(0, 'data');

        $response = $this->actingAs($admin)->json('GET', '/api/admin/notifications/all', [
            'date_to' => Carbon::now()->subDay()->toIso8601String(),
        ]);
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        $response = $this->actingAs($admin)->json('GET', '/api/admin/notifications/all', [
            'date_from' => Carbon::now()->subDay()->toIso8601String(),
            'date_to' => Carbon::now()->addDay()->toIso8601String(),
        ]);
        $response->assertOk();

        $response->assertJsonCount(3, 'data');

        $response = $this->actingAs($admin)->json('GET', '/api/admin/notifications/all', [
            'date_from' => Carbon::now()->subDay()->toIso8601String(),
            'date_to' => Carbon::now()->addDay()->toIso8601String(),
            'event' => TestEvent::class,
        ]);
        $response->assertOk();

        $response->assertJsonCount(2, 'data');
    }

    public function test_user_pagination(): void
    {
        $user = $this->makeStudent();
        $this->generateEvents($user, 25);

        $this->actingAs($user, 'api')
            ->getJson('/api/notifications?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'per_page' => 10,
                    'total' => 25
                ]
            ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/notifications?per_page=10&page=3')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'per_page' => 10,
                    'total' => 25
                ]
            ]);
    }

    public function test_user_notifications_sorting(): void
    {
        $user = $this->makeStudent();
        $this->generateEvents($user, 10);

        $notifications = DatabaseNotification::where('notifiable_id', $user->getKey())->get();
        $first = $notifications->first();
        $first->update(['created_at' => now()->addSecond()]);

        $last = $notifications->last();
        $last->update(['created_at' => now()->subDays(2)]);

        $this->actingAs($user, 'api')
            ->getJson('/api/notifications?order_by=created_at')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.id', $last->getKey());

        $this->actingAs($user, 'api')
            ->getJson('/api/notifications?order_by=created_at&order=DESC')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.id', $first->getKey());
    }

    public function test_admin_pagination(): void
    {
        $this->generateEvents(null, 13);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/admin/notifications/all?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'per_page' => 10,
                    'total' => 13
                ]
            ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/admin/notifications/all?per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'meta' => [
                    'per_page' => 10,
                    'total' => 13
                ]
            ]);
    }

    private function generateEvents(?User $user = null, int $count = 5): void
    {
        $u1 = $user ?? $this->makeAdmin();
        $u2 = $this->makeStudent();

        for ($i = 0; $i < $count; $i++) {
            event(new TestEvent($u1, $u2, $this->faker->word));
        }
    }
}
