<?php

namespace EscolaLms\BulkNotifications\Channels;

use EscolaLms\BulkNotifications\ValueObjects\Notification;
use Illuminate\Support\Collection;

interface NotificationChannel
{
    public function send(Notification $notification): void;

    public static function sections(): Collection;

    public static function requiredSections(): Collection;
}
