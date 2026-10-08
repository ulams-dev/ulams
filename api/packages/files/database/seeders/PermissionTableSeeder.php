<?php

namespace EscolaLms\Files\Database\Seeders;

use EscolaLms\Files\Enums\FilePermissionsEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin', 'api');
        $tutor = Role::findOrCreate('tutor', 'api');
        $permissions = [
            FilePermissionsEnum::FILE_LIST,
            FilePermissionsEnum::FILE_LIST_SELF,
            FilePermissionsEnum::FILE_DELETE,
            FilePermissionsEnum::FILE_UPDATE,
            FilePermissionsEnum::FILE_CREATE,
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo($permissions);
        $tutor->givePermissionTo($permissions);
    }
}
