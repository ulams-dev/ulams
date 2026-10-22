<?php

namespace Ulams\Identity\Enums;

use Ulams\Core\Enums\BasicEnum;

class IdentityPermissionEnum extends BasicEnum
{
    /** Create, change and delete identity providers. */
    public const PROVIDER_MANAGE = 'identity_provider_manage';
    /** Read the identity audit log and verify its chain. */
    public const AUDIT_READ = 'identity_audit_read';

    public static function adminPermissions(): array
    {
        return [self::PROVIDER_MANAGE, self::AUDIT_READ];
    }
}
