<?php

namespace EscolaLms\Categories\Database\Seeders;

use EscolaLms\Categories\Enums\CategoriesPermissionsEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use Illuminate\Database\Seeder;

class CategoriesPermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate('admin', 'api');
        $tutor = Role::findOrCreate('tutor', 'api');

        foreach (CategoriesPermissionsEnum::asArray() as $const => $value) {
            Permission::findOrCreate($value, 'api');
        }

        $admin->givePermissionTo([
            CategoriesPermissionsEnum::CATEGORY_LIST,
            CategoriesPermissionsEnum::CATEGORY_READ,
            CategoriesPermissionsEnum::CATEGORY_CREATE,
            CategoriesPermissionsEnum::CATEGORY_DELETE,
            CategoriesPermissionsEnum::CATEGORY_UPDATE,
        ]);
        $tutor->givePermissionTo([
            CategoriesPermissionsEnum::CATEGORY_LIST,
            CategoriesPermissionsEnum::CATEGORY_READ,
            CategoriesPermissionsEnum::CATEGORY_CREATE,
            CategoriesPermissionsEnum::CATEGORY_DELETE,
            CategoriesPermissionsEnum::CATEGORY_UPDATE,
        ]);
    }
}
