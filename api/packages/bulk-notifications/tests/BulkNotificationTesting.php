<?php

namespace EscolaLms\BulkNotifications\Tests;

use EscolaLms\BulkNotifications\Channels\PushNotificationChannel;
use EscolaLms\BulkNotifications\Dtos\SendMulticastBulkNotificationDto;
use EscolaLms\BulkNotifications\Dtos\SendUserBulkNotificationDto;
use EscolaLms\BulkNotifications\Models\BulkNotification;
use EscolaLms\BulkNotifications\Models\BulkNotificationSection;
use EscolaLms\BulkNotifications\Models\DeviceToken;
use Illuminate\Foundation\Testing\WithFaker;

trait BulkNotificationTesting
{
    use WithFaker;

    public function bulkNotificationTable(): string
    {
        return with(new BulkNotification())->getTable();
    }

    public function bulkNotificationSectionTable(): string
    {
        return with(new BulkNotificationSection())->getTable();
    }

    public function bulkNotificationUserTable(): string
    {
        return 'bulk_notification_user';
    }

    public function makeUserBulkNotificationPayload(?string $channel, ?array $data = [], ?bool $withUsers = true): array
    {
        if (!$channel) {
            return [
                'channel' => $channel
            ];
        }

        $payload = [
            'channel' => $channel,
            'sections' => [
                ...$channel::sections()
                    ->mapWithKeys(fn(string $section) => [$section => $this->faker->words(5, true)])
            ],
            'user_ids' => $this->makeUsers($channel, rand(1, 10)),
        ];

        return array_merge($payload, $data);
    }

    public function makeMulticastBulkNotificationPayload(?string $channel, ?array $data = []): array
    {
        if (!$channel) {
            return [
                'channel' => $channel
            ];
        }

        $payload = [
            'channel' => $channel,
            'sections' => [
                ...$channel::sections()
                    ->mapWithKeys(fn(string $section) => [$section => $this->faker->words(5, true)])
            ]
        ];

        return array_merge($payload, $data);
    }

    public function makeSendUserBulkNotificationDto(string $channel, ?array $sections = []): SendUserBulkNotificationDto
    {
        return new SendUserBulkNotificationDto(
            $channel,
            $channel::sections()
                ->mapWithKeys(fn(string $section) => [$section => 'value'])
                ->merge($sections)
                ->toArray(),
            $this->makeUsers($channel, rand(1, 10)),
        );
    }

    public function makeSendMulticastUserBulkNotificationDto(string $channel, ?array $sections = []): SendMulticastBulkNotificationDto
    {
        return new SendMulticastBulkNotificationDto(
            $channel,
            $channel::sections()
                ->mapWithKeys(fn(string $section) => [$section => 'value'])
                ->merge($sections)
                ->toArray()
        );
    }

    public function assertBulkNotification(int $bulkNotificationId, string $channel): void
    {
        $this->assertDatabaseHas($this->bulkNotificationTable(), [
            'id' => $bulkNotificationId,
            'channel' => $channel,
        ]);
    }

    public function assertBulkNotificationHasSections(array $data): void
    {
        $sections = collect($data)->map(fn($value, $key) => [
            'key' => $key,
            'value' => $value
        ]);

        $sections->each(fn(array $section) => $this->assertDatabaseHas($this->bulkNotificationSectionTable(), $section));

        $this->assertDatabaseCount($this->bulkNotificationSectionTable(), $sections->count());
    }

    public function assertBulkNotificationHasUsers(int $bulkNotificationId, array $data): void
    {
        $users = collect($data)->map(fn($value) => [
            'bulk_notification_id' => $bulkNotificationId,
            'user_id' => $value
        ]);

        $users->each(fn(array $user) => $this->assertDatabaseHas($this->bulkNotificationUserTable(), $user));

        $this->assertDatabaseCount($this->bulkNotificationUserTable(), $users->count());
    }

    public function assertBulkNotificationMissingUsers(int $bulkNotificationId, array $data): void
    {
        $users = collect($data)->map(fn($value) => [
            'bulk_notification_id' => $bulkNotificationId,
            'user_id' => $value
        ]);

        $users->each(fn(array $user) => $this->assertDatabaseMissing($this->bulkNotificationUserTable(), $user));
    }


    public function makeUsers(string $channel, int $count): array
    {
        return match ($channel) {
            PushNotificationChannel::class => DeviceToken::factory()
                ->count($count)
                ->create()
                ->pluck('user_id')
                ->toArray(),
            default => collect()
        };
    }
}
