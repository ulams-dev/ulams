<?php

namespace Ulams\BulkNotifications\Events;

use Ulams\BulkNotifications\Models\BulkNotification;
use Ulams\BulkNotifications\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent
{
    use Dispatchable, SerializesModels;

    private BulkNotification $notification;

    private User $user;

    public function __construct(BulkNotification $notification, User $user)
    {
        $this->notification = $notification;
        $this->user = $user;
    }

    public function getNotification(): BulkNotification
    {
        return $this->notification;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
