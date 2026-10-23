<?php

namespace Ulams\PolicyLifecycle\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Ulams\Core\Enums\UserRole;
use Ulams\PolicyLifecycle\Enums\PolicyPermissionsEnum;

class PolicyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PolicyPermissionsEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        Role::findOrCreate(UserRole::ADMIN, 'api')->givePermissionTo(PolicyPermissionsEnum::getValues());
    }
}
