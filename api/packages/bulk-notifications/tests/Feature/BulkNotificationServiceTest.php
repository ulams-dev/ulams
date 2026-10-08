<?php

namespace EscolaLms\BulkNotifications\Tests\Feature;

use EscolaLms\BulkNotifications\Channels\PushNotificationChannel;
use EscolaLms\BulkNotifications\Database\Seeders\BulkNotificationPermissionSeeder;
use EscolaLms\BulkNotifications\Dtos\SendMulticastBulkNotificationDto;
use EscolaLms\BulkNotifications\Dtos\SendUserBulkNotificationDto;
use EscolaLms\BulkNotifications\Events\NotificationSent;
use EscolaLms\BulkNotifications\Exceptions\UnsupportedNotification;
use EscolaLms\BulkNotifications\Services\Contracts\BulkNotificationServiceContract;
use EscolaLms\BulkNotifications\Tests\BulkNotificationTesting;
use EscolaLms\BulkNotifications\Tests\FakeNotificationChannel;
use EscolaLms\BulkNotifications\Tests\TestCase;
use EscolaLms\Core\Models\User;
use Exception;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

class BulkNotificationServiceTest extends TestCase
{
    use BulkNotificationTesting;

    private BulkNotificationServiceContract $bulkNotificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BulkNotificationPermissionSeeder::class);

        $this->bulkNotificationService = app(BulkNotificationServiceContract::class);

        Event::fake([NotificationSent::class]);
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendUserNotificationViaChannel(string $channel): void
    {
        $dto = $this->makeSendUserBulkNotificationDto($channel);
        $amount = $dto->getUserIds()->count();
        $users = User::factory()->count(5)->create()->pluck('id')->toArray();

        $this->mock($channel, fn (MockInterface $mock) => $mock->shouldReceive('send')->times($amount));

        $bulkNotification = $this->bulkNotificationService->send($dto);

        $this->assertBulkNotification($bulkNotification->getKey(), $channel);
        $this->assertBulkNotificationHasSections($dto->getSections()->toArray());
        $this->assertBulkNotificationHasUsers($bulkNotification->getKey(), $dto->getUserIds()->toArray());
        $this->assertBulkNotificationMissingUsers($bulkNotification->getKey(), $users);

        Event::assertDispatchedTimes(NotificationSent::class, $amount);
        Event::assertDispatched(NotificationSent::class, function (NotificationSent $event) use ($bulkNotification, $dto) {
            return $dto->getUserIds()->contains($event->getUser()->getKey()) && $event->getNotification()->getKey() === $bulkNotification->getKey();
        });
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendMulticastNotificationViaChannel(string $channel): void
    {
        $dto = $this->makeSendMulticastUserBulkNotificationDto($channel);
        $channelUsers = $this->makeUsers($channel, 10);
        $users = User::factory()->count(5)->create()->pluck('id')->toArray();
        $notifiedUsersCount = count($channelUsers) + count($users);
        $notifiedUsers = array_merge($channelUsers, $users);

        $this->mock($channel, fn (MockInterface $mock) => $mock->shouldReceive('send')->times(count($channelUsers)));

        $bulkNotification = $this->bulkNotificationService->sendMulticast($dto);

        $this->assertBulkNotification($bulkNotification->getKey(), $channel);
        $this->assertBulkNotificationHasSections($dto->getSections()->toArray());
        $this->assertBulkNotificationHasUsers($bulkNotification->getKey(), $notifiedUsers);

        Event::assertDispatchedTimes(NotificationSent::class, $notifiedUsersCount);
        Event::assertDispatched(NotificationSent::class, function (NotificationSent $event) use ($bulkNotification, $notifiedUsers) {
            return collect($notifiedUsers)->contains($event->getUser()->getKey()) && $event->getNotification()->getKey() === $bulkNotification->getKey();
        });
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendUserNotificationViaChannelFilterSections(string $channel): void
    {
        $dto = $this->makeSendUserBulkNotificationDto($channel, ['invalid_section_key_1' => 'value', 'invalid_section_key_2' => 'value']);
        $amount = $dto->getUserIds()->count();

        $this->mock($channel, fn (MockInterface $mock) => $mock->shouldReceive('send')->times($amount));

        $bulkNotification = $this->bulkNotificationService->send($dto);

        $this->assertBulkNotification($bulkNotification->getKey(), $channel);
        $this->assertBulkNotificationHasSections($dto->getSections()->except('invalid_section_key_1', 'invalid_section_key_2')->toArray());
        $this->assertBulkNotificationHasUsers($bulkNotification->getKey(), $dto->getUserIds()->toArray());

        $this->assertDatabaseMissing($this->bulkNotificationSectionTable(), [
            'bulk_notification_id' => $bulkNotification->getKey(),
            'key' => 'invalid_section_key_1',
            'value' => 'value'
        ]);
        $this->assertDatabaseMissing($this->bulkNotificationSectionTable(), [
            'bulk_notification_id' => $bulkNotification->getKey(),
            'key' => 'invalid_section_key_2',
            'value' => 'value'
        ]);

        Event::assertDispatchedTimes(NotificationSent::class, $amount);
    }

    /**
     * @dataProvider channelDataProvider
     */
    public function testSendMulticastNotificationViaChannelFilterSections(string $channel): void
    {
        $dto = $this->makeSendMulticastUserBulkNotificationDto($channel, ['invalid_section_key_1' => 'value', 'invalid_section_key_2' => 'value']);
        $notifiedUsersCount = 10;
        $notifiedUsers = $this->makeUsers($channel, $notifiedUsersCount);

        $this->mock($channel, fn (MockInterface $mock) => $mock->shouldReceive('send')->times($notifiedUsersCount));

        $bulkNotification = $this->bulkNotificationService->sendMulticast($dto);

        $this->assertBulkNotification($bulkNotification->getKey(), $channel);
        $this->assertBulkNotificationHasSections($dto->getSections()->except('invalid_section_key_1', 'invalid_section_key_2')->toArray());
        $this->assertBulkNotificationHasUsers($bulkNotification->getKey(), $notifiedUsers);

        $this->assertDatabaseMissing($this->bulkNotificationSectionTable(), [
            'bulk_notification_id' => $bulkNotification->getKey(),
            'key' => 'invalid_section_key_1',
            'value' => 'value'
        ]);
        $this->assertDatabaseMissing($this->bulkNotificationSectionTable(), [
            'bulk_notification_id' => $bulkNotification->getKey(),
            'key' => 'invalid_section_key_2',
            'value' => 'value'
        ]);

        Event::assertDispatchedTimes(NotificationSent::class, $notifiedUsersCount);
    }

    public function testSendUserNotificationUnsupportedChannel(): void
    {
        $fakeChannelClass = FakeNotificationChannel::class;
        $dto = new SendUserBulkNotificationDto($fakeChannelClass, [], []);

        $this->expectException(UnsupportedNotification::class);

        $this->bulkNotificationService->send($dto);

        Event::assertNotDispatched(NotificationSent::class);
    }

    public function testSendMulticastNotificationUnsupportedChannel(): void
    {
        $fakeChannelClass = FakeNotificationChannel::class;
        $dto = new SendMulticastBulkNotificationDto($fakeChannelClass, []);

        $this->expectException(UnsupportedNotification::class);

        $this->bulkNotificationService->sendMulticast($dto);

        Event::assertNotDispatched(NotificationSent::class);
    }

    public function channelDataProvider(): array
    {
        return [
            ['channel' => PushNotificationChannel::class]
        ];
    }
}
