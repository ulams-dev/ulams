<?php

namespace EscolaLms\Pages\Database\Seeders;

use EscolaLms\Pages\Enums\PagesPermissionsEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $apiAdmin = Role::findOrCreate('admin', 'api');
        $permissions = [
            PagesPermissionsEnum::PAGE_LIST,
            PagesPermissionsEnum::PAGE_READ,
            PagesPermissionsEnum::PAGE_DELETE,
            PagesPermissionsEnum::PAGE_UPDATE,
            PagesPermissionsEnum::PAGE_CREATE,
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $apiAdmin->givePermissionTo($permissions);
    }
}
