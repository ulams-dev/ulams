<?php

namespace EscolaLms\Permissions\Database\Seeders;

use EscolaLms\Permissions\Enums\PermissionsPermissionsEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @todo remove neccesity of using 'web' guard
 */
class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $apiAdmin = Role::findOrCreate('admin', 'api');
        $permissions = [
            PermissionsPermissionsEnum::PERMISSIONS_ROLE_MANAGE,
            PermissionsPermissionsEnum::PERMISSIONS_ROLE_LIST,
            PermissionsPermissionsEnum::PERMISSIONS_ROLE_READ,
            PermissionsPermissionsEnum::PERMISSIONS_ROLE_CREATE,
            PermissionsPermissionsEnum::PERMISSIONS_ROLE_UPDATE,
            PermissionsPermissionsEnum::PERMISSIONS_ROLE_DELETE,
        ];


        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $apiAdmin->givePermissionTo($permissions);
    }
}
