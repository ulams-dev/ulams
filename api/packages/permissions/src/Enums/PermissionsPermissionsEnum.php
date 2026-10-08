<?php

namespace EscolaLms\Permissions\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class PermissionsPermissionsEnum extends BasicEnum
{
    const PERMISSIONS_ROLE_MANAGE = 'permission_role_manage';
    const PERMISSIONS_ROLE_LIST = 'permission_role_list';
    const PERMISSIONS_ROLE_READ = 'permission_role_read';
    const PERMISSIONS_ROLE_CREATE = 'permission_role_create';
    const PERMISSIONS_ROLE_UPDATE = 'permission_role_update';
    const PERMISSIONS_ROLE_DELETE = 'permission_role_delete';
}
