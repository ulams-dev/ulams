<?php

namespace Ulams\Identity\Enums;

use Ulams\Core\Enums\BasicEnum;

class IdentityPermissionEnum extends BasicEnum
{
    /** Create, change and delete identity providers. */
    public const PROVIDER_MANAGE = 'identity_provider_manage';
    /** Read the identity audit log and verify its chain. */
    public const AUDIT_READ = 'identity_audit_read';

    /** Create and revoke the SCIM tokens of identity providers. */
    public const SCIM_MANAGE = 'identity_scim_manage';

    public static function adminPermissions(): array
    {
        return [self::PROVIDER_MANAGE, self::AUDIT_READ, self::SCIM_MANAGE];
    }
}
