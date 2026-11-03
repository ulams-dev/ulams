<?php

namespace Ulams\Webhooks\Enums;

use Ulams\Core\Enums\BasicEnum;

class WebhookPermissionEnum extends BasicEnum
{
    /** Create, change, delete, test and replay webhook endpoints and read their delivery log. */
    public const WEBHOOK_MANAGE = 'webhook_manage';

    public static function adminPermissions(): array
    {
        return [self::WEBHOOK_MANAGE];
    }
}
