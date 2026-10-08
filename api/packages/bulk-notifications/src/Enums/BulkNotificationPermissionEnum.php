<?php

namespace Ulams\BulkNotifications\Enums;

use Ulams\Core\Enums\BasicEnum;

class BulkNotificationPermissionEnum extends BasicEnum
{
    public const CREATE_DEVICE_TOKEN = 'device-token_create';

    public const CREATE_BULK_NOTIFICATION = 'bulk-notification_create';
    public const LIST_BULK_NOTIFICATION = 'bulk-notification_list';
}
