<?php

namespace Ulams\ExamplePlugin\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Core\Enums\UserRole;
use Ulams\ExamplePlugin\Enums\ExamplePluginPermissionEnum;

class ExamplePluginPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');

        foreach (ExamplePluginPermissionEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo(ExamplePluginPermissionEnum::adminPermissions());
    }
}
