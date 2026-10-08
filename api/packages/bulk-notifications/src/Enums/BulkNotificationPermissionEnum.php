<?php

namespace EscolaLms\BulkNotifications\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class BulkNotificationPermissionEnum extends BasicEnum
{
    public const CREATE_DEVICE_TOKEN = 'device-token_create';

    public const CREATE_BULK_NOTIFICATION = 'bulk-notification_create';
    public const LIST_BULK_NOTIFICATION = 'bulk-notification_list';
}
