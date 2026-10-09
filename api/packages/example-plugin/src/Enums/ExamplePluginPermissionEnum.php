<?php

namespace Ulams\ExamplePlugin\Enums;

use Ulams\Core\Enums\BasicEnum;

class ExamplePluginPermissionEnum extends BasicEnum
{
    public const SEND_GREETING = 'example-plugin_send-greeting';

    public static function adminPermissions(): array
    {
        return [
            self::SEND_GREETING,
        ];
    }
}
