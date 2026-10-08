<?php

namespace EscolaLms\BulkNotifications\Tests\Api;

use EscolaLms\BulkNotifications\Channels\PushNotificationChannel;
use EscolaLms\BulkNotifications\Database\Seeders\BulkNotificationPermissionSeeder;
use EscolaLms\BulkNotifications\Jobs\SendNotification;
use EscolaLms\Core\Models\User;
use EscolaLms\BulkNotifications\Tests\BulkNotificationTesting;
use EscolaLms\BulkNotifications\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Support\Facades\Queue;

class SendMulticastBulkNotificationApiTest extends TestCase
{
    use CreatesUsers, BulkNotificationTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BulkNotificationPermissionSeeder::class);

        Queue::fake();
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendMulticastBulkNotification(string $channel): void
    {
        $user = $this->makeAdmin();
        $payload = $this->makeMulticastBulkNotificationPayload($channel);
        $channelUsers = $this->makeUsers($channel, 5);
        $users = User::factory()->count(10)->create()->pluck('id')->toArray();

        $response = $this->actingAs($user, 'api')
            ->postJson('api/admin/bulk-notifications/send/multicast', $payload)
            ->assertCreated();

        $bulkNotificationId = $response->json('data.id');

        $this->assertBulkNotification($bulkNotificationId, $channel);
        $this->assertBulkNotificationHasSections($payload['sections']);
        $this->assertBulkNotificationHasUsers($bulkNotificationId, array_merge([$user->getKey()], $users, $channelUsers));

        Queue::assertPushed(SendNotification::class);
    }

    public function testSendMulticastBulkNotificationInvalidChannel(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/bulk-notifications/send/multicast', $this->makeMulticastBulkNotificationPayload(null))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/bulk-notifications/send/multicast', $this->makeMulticastBulkNotificationPayload(''))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testSendMulticastBulkNotificationInvalidSections(string $channel, array $data, array $errors): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/bulk-notifications/send/multicast', $this->makeMulticastBulkNotificationPayload($channel, $data))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errors);
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendMulticastBulkNotificationForbidden(string $channel): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/admin/bulk-notifications/send/multicast', $this->makeMulticastBulkNotificationPayload($channel))
            ->assertForbidden();
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendMulticastBulkNotificationUnauthorized(string $channel): void
    {
        $this->postJson('api/admin/bulk-notifications/send/multicast', $this->makeUserBulkNotificationPayload($channel))
            ->assertUnauthorized();
    }

    public function invalidDataProvider(): array
    {
        return [
            ['channel' => PushNotificationChannel::class, 'data' => ['sections' => ['title' => null, 'body' => 'Content']], 'errors' => ['sections.title']],
            ['channel' => PushNotificationChannel::class, 'data' => ['sections' => ['title' => '', 'body' => 'Content']], 'errors' => ['sections.title']],
            ['channel' => PushNotificationChannel::class, 'data' => ['sections' => ['title' => 'Test', 'body' => null]], 'errors' => ['sections.body']],
            ['channel' => PushNotificationChannel::class, 'data' => ['sections' => ['title' => 'Test', 'body' => '']], 'errors' => ['sections.body']],
            ['channel' => PushNotificationChannel::class, 'data' => ['sections' => ['title' => 'Test']], 'errors' => ['sections']],
            ['channel' => PushNotificationChannel::class, 'data' => ['sections' => ['body' => 'Content']], 'errors' => ['sections']],
            ['channel' => PushNotificationChannel::class, 'data' => ['sections' => ['body' => 'Content']], 'errors' => ['sections']],
            ['channel' => PushNotificationChannel::class, 'data' => ['sections' => ['body' => 'Content']], 'errors' => ['sections']],
        ];
    }
}
