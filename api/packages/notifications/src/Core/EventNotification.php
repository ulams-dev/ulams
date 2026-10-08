<?php

namespace EscolaLms\Notifications\Core;

use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification as IlluminateNotification;

class EventNotification extends IlluminateNotification
{
    private object $event;
    private array $data;

    public function __construct(object $event, array $data)
    {
        $this->event = $event;
        $this->data = $data;
    }

    public function via(): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage($this->toArray($notifiable));
    }

    public function getEvent(): object
    {
        return $this->event;
    }
}
