<?php

namespace Ulams\BulkNotifications\Tests;

use Ulams\BulkNotifications\Channels\NotificationChannel;
use Ulams\BulkNotifications\ValueObjects\Notification;
use Illuminate\Support\Collection;

class FakeNotificationChannel implements NotificationChannel
{
    public function __construct(
    )
    {
    }

    public function send(Notification $notification): void
    {
    }

    public static function sections(): Collection
    {
        return collect();
    }

    public static function requiredSections(): Collection
    {
        return collect();
    }
}
