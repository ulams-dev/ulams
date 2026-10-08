<?php

namespace EscolaLms\BulkNotifications\Channels;

use EscolaLms\BulkNotifications\EscolaLmsBulkNotificationsServiceProvider;
use EscolaLms\BulkNotifications\Exceptions\UnsupportedNotification;
use EscolaLms\BulkNotifications\ValueObjects\Notification;
use EscolaLms\BulkNotifications\ValueObjects\PushNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class PushNotificationChannel implements NotificationChannel
{
    private Messaging $messaging;

    public function __construct()
    {
        $this->messaging = (new Factory())
            ->withServiceAccount($this->getServiceAccount())
            ->createMessaging();
    }

    /**
     * @throws MessagingException
     * @throws FirebaseException
     * @throws UnsupportedNotification
     */
    public function send(Notification $notification): void
    {
        $message = $this->makeCloudMessage($notification);
        $this->messaging->send($message);
    }

    /**
     * @throws UnsupportedNotification
     */
    private function makeCloudMessage(Notification $notification): CloudMessage
    {
        if (!$notification instanceof PushNotification) {
            throw new UnsupportedNotification();
        }

        return CloudMessage::fromArray($notification->toPayload());
    }

    private function getServiceAccount(): array
    {
        return json_decode(Config::get(EscolaLmsBulkNotificationsServiceProvider::CONFIG_KEY . '.push.service_account'), true) ?? [];
    }

    public static function sections(): Collection
    {
        return collect([
            'title',
            'body',
            'image_url',
            'redirect_url',
            'data',
        ]);
    }

    public static function requiredSections(): Collection
    {
        return collect([
            'title',
            'body',
        ]);
    }
}
