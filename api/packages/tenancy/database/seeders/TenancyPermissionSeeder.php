<?php

namespace Ulams\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Tenancy\Enums\TenancyPermissionEnum;

class TenancyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        foreach (TenancyPermissionEnum::asArray() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        $admin->givePermissionTo(TenancyPermissionEnum::adminPermissions());
    }
}
