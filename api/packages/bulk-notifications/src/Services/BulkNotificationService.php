<?php

namespace Ulams\BulkNotifications\Services;

use Ulams\BulkNotifications\Channels\NotificationChannel;
use Ulams\BulkNotifications\Channels\PushNotificationChannel;
use Ulams\BulkNotifications\Dtos\OrderDto;
use Ulams\BulkNotifications\Dtos\PageDto;
use Ulams\BulkNotifications\Dtos\SendBulkNotificationDto;
use Ulams\BulkNotifications\Dtos\SendUserBulkNotificationDto;
use Ulams\BulkNotifications\Dtos\SendMulticastBulkNotificationDto;
use Ulams\BulkNotifications\Events\NotificationSent;
use Ulams\BulkNotifications\Exceptions\UnsupportedNotification;
use Ulams\BulkNotifications\Jobs\SendNotification;
use Ulams\BulkNotifications\Models\BulkNotification;
use Ulams\BulkNotifications\Models\BulkNotificationUser;
use Ulams\BulkNotifications\Models\DeviceToken;
use Ulams\BulkNotifications\Models\User;
use Ulams\BulkNotifications\Repositories\Contracts\BulkNotificationRepositoryContract;
use Ulams\BulkNotifications\Repositories\Contracts\DeviceTokenRepositoryContract;
use Ulams\BulkNotifications\Repositories\Contracts\UserRepositoryContract;
use Ulams\BulkNotifications\Services\Contracts\BulkNotificationServiceContract;
use Ulams\BulkNotifications\ValueObjects\PushNotification;
use Ulams\BulkNotifications\Dtos\CriteriaBulkNotificationDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkNotificationService implements BulkNotificationServiceContract
{

    public function __construct(
        private BulkNotificationRepositoryContract $bulkNotificationRepository,
        private DeviceTokenRepositoryContract $deviceTokenRepository,
        private UserRepositoryContract $userRepository,
    )
    {
    }

    public function send(SendUserBulkNotificationDto $dto): BulkNotification
    {
        return DB::transaction(function () use ($dto) {
            /** @var NotificationChannel $channel */
            $channel = $dto->getChannel();

            $bulkNotification = $this->createBulkNotification($dto, $channel::sections());

            $recipients = $this->recipients($bulkNotification, $dto->getUserIds());

            $this->createBulkNotificationUsers($bulkNotification, $dto->getUserIds());

            $this->process($bulkNotification, $recipients);

            return $bulkNotification;
        });
    }

    public function sendMulticast(SendMulticastBulkNotificationDto $dto): BulkNotification
    {
        return DB::transaction(function () use ($dto) {
            /** @var NotificationChannel $channel */
            $channel = $dto->getChannel();

            $bulkNotification = $this->createBulkNotification($dto, $channel::sections());

            $recipients = $this->recipients($bulkNotification);

            $this->createBulkNotificationUsers($bulkNotification);

            $this->process($bulkNotification, $recipients);

            return $bulkNotification;
        });
    }

    public function list(CriteriaBulkNotificationDto $criteriaDto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator
    {
        return $this->bulkNotificationRepository->findAll(
            $criteriaDto->toArray(),
            $pageDto->getPerPage(),
            $orderDto->getOrderDirection(),
            $orderDto->getOrderBy()
        );
    }

    private function recipients(BulkNotification $bulkNotification, ?Collection $userIds = null): Collection
    {
        return match ($bulkNotification->channel) {
            PushNotificationChannel::class => $userIds ? $this->deviceTokenRepository->findUsersTokens($userIds) : $this->deviceTokenRepository->findTokens(),
            default => throw new UnsupportedNotification()
        };
    }

    /**
     * @throws UnsupportedNotification
     */
    private function process(BulkNotification $bulkNotification, Collection $recipients): void
    {
        match ($bulkNotification->channel) {
            PushNotificationChannel::class => $this->processPushNotifications($bulkNotification, $recipients),
            default => throw new UnsupportedNotification()
        };
    }

    private function processPushNotifications(BulkNotification $bulkNotification, Collection $recipients): void
    {
        $identifier = $bulkNotification->getKey();
        $channel = $bulkNotification->channel;
        $users = $bulkNotification->users;
        $sections = $bulkNotification->sections;

        $recipients
            ->each(function (DeviceToken $deviceToken) use ($identifier, $channel, $users, $sections, $recipients) {
                $notification = PushNotification::of(
                    $users->filter(fn(User $user) => $user->getKey() === $deviceToken->user->getKey())->first()?->pivot,
                    $identifier,
                    $sections,
                    $deviceToken->token
                );

                SendNotification::dispatch($notification, $channel);
            });
    }

    private function createBulkNotification(SendBulkNotificationDto $dto, Collection $channelSections): BulkNotification
    {
        /** @var BulkNotification $bulkNotification */
        $bulkNotification = $this->bulkNotificationRepository->create($dto->toArray());
        $filteredSections = $dto->getSections()->filter(fn($value, $key) => $channelSections->contains($key));

        foreach ($filteredSections as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $bulkNotification->sections()->create(['key' => $key, 'value' => $value]);
        }

        return $bulkNotification;
    }

    private function createBulkNotificationUsers(BulkNotification $bulkNotification, ?Collection $userIds = null): void
    {
        if (!$userIds) {
            $userIds = $this->userRepository->findAllIds();
        }

        $bulkNotification->users()->attach($userIds);

        $this->dispatchNotificationEvents($bulkNotification, $bulkNotification->users);
    }

    private function dispatchNotificationEvents(BulkNotification $bulkNotification, Collection $users): void
    {
        $bulkNotification->load('sections')->unsetRelation('users');
        $users->each(fn(User $user) => NotificationSent::dispatch($bulkNotification, $user));
    }
}
