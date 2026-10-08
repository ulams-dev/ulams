<?php

namespace EscolaLms\Notifications\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class NotificationsPermissionsEnum extends BasicEnum
{

    const READ_ALL_NOTIFICATIONS        = 'dashboard-app_notification-list_access';
    const READ_NOTIFICATION_EVENTS_LIST = 'dashboard-app_notification-list_access_self';
}
