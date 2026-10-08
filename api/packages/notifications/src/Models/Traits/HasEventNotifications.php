<?php

namespace Ulams\Notifications\Models\Traits;

use Ulams\Notifications\Models\DatabaseNotification;
use Illuminate\Notifications\HasDatabaseNotifications;

trait HasEventNotifications
{
    use HasDatabaseNotifications;

    public function notifications()
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    public function routeNotificationForDatabase()
    {
        return $this->notifications();
    }
}
