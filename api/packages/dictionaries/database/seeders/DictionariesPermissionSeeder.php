<?php

namespace EscolaLms\Dictionaries\Database\Seeders;

use EscolaLms\Dictionaries\Enums\DictionariesPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DictionariesPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');

        foreach (DictionariesPermissionEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo(DictionariesPermissionEnum::getAdminPermissions());
    }
}
