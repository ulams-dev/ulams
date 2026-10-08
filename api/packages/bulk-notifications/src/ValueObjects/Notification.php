<?php

namespace EscolaLms\BulkNotifications\ValueObjects;

use EscolaLms\BulkNotifications\Models\BulkNotificationUser;
use Illuminate\Support\Collection;

abstract class Notification
{

    public function __construct(protected BulkNotificationUser $bulkNotificationUser, protected int $identifier)
    {
    }

    public function getBulkNotificationUser(): BulkNotificationUser
    {
        return $this->bulkNotificationUser;
    }

    public function getIdentifier(): int
    {
        return $this->identifier;
    }

    abstract public static function of(BulkNotificationUser $bulkNotificationUser, int $identifier, Collection $sections, string $destination): Notification;

    abstract public function toPayload();
}
