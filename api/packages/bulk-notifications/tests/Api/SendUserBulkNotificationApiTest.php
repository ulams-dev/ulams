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

class SendUserBulkNotificationApiTest extends TestCase
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
    public function testSendUserBulkNotification(string $channel): void
    {
        $user = $this->makeAdmin();
        $payload = $this->makeUserBulkNotificationPayload($channel);
        $users = User::factory()->count(10)->create()->pluck('id')->toArray();

        $response = $this->actingAs($user, 'api')
            ->postJson('api/admin/bulk-notifications/send', $payload)
            ->assertCreated();

        $bulkNotificationId = $response->json('data.id');

        $this->assertBulkNotification($bulkNotificationId, $channel);
        $this->assertBulkNotificationHasSections($payload['sections']);
        $this->assertBulkNotificationHasUsers($bulkNotificationId, $payload['user_ids']);
        $this->assertBulkNotificationMissingUsers($bulkNotificationId, $users);

        Queue::assertPushed(SendNotification::class);
    }

    public function testSendUserBulkNotificationInvalidChannel(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/bulk-notifications/send', $this->makeUserBulkNotificationPayload(null))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/bulk-notifications/send', $this->makeUserBulkNotificationPayload(''))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testSendUserBulkNotificationInvalidSections(string $channel, array $data, array $errors): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/bulk-notifications/send', $this->makeUserBulkNotificationPayload($channel, $data))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errors);
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendUserBulkNotificationForbidden(string $channel): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/admin/bulk-notifications/send', $this->makeUserBulkNotificationPayload($channel))
            ->assertForbidden();
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendUserBulkNotificationUnauthorized(string $channel): void
    {
        $this->postJson('api/admin/bulk-notifications/send', $this->makeUserBulkNotificationPayload($channel))
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
            ['channel' => PushNotificationChannel::class, 'data' => ['user_ids' => []], 'errors' => ['user_ids']],
            ['channel' => PushNotificationChannel::class, 'data' => ['user_ids' => [999, 9999]], 'errors' => ['user_ids.0', 'user_ids.1']],
        ];
    }
}
