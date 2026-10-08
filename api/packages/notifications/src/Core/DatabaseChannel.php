<?php

namespace EscolaLms\Notifications\Core;

use EscolaLms\Notifications\Models\User;
use Illuminate\Notifications\Channels\DatabaseChannel as IlluminateDatabaseChannel;
use Illuminate\Notifications\Notification;

class DatabaseChannel extends IlluminateDatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        $notifiable = User::find($notifiable->getKey());
        return parent::send($notifiable, $notification);
    }

    protected function buildPayload($notifiable, Notification $notification)
    {
        return array_merge(parent::buildPayload($notifiable, $notification), [
            'event' => $notification instanceof EventNotification ? get_class($notification->getEvent()) : null,
        ]);
    }
}
