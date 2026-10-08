<?php

namespace Ulams\BulkNotifications\Channels;

use Ulams\BulkNotifications\ValueObjects\Notification;
use Illuminate\Support\Collection;

interface NotificationChannel
{
    public function send(Notification $notification): void;

    public static function sections(): Collection;

    public static function requiredSections(): Collection;
}
