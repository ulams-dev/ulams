<?php

namespace Ulams\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Identity\Enums\IdentityPermissionEnum;

class IdentityPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        foreach (IdentityPermissionEnum::asArray() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        $admin->givePermissionTo(IdentityPermissionEnum::adminPermissions());
    }
}
