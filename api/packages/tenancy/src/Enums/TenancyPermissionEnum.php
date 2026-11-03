<?php

namespace Ulams\Tenancy\Enums;

use Ulams\Core\Enums\BasicEnum;

class TenancyPermissionEnum extends BasicEnum
{
    /** Add, change, disable and delete the branded entry points (hosts) of the academy. */
    public const ENTRY_POINTS_MANAGE = 'entry_points_manage';

    public static function adminPermissions(): array
    {
        return [self::ENTRY_POINTS_MANAGE];
    }
}
