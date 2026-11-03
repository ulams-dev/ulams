<?php

namespace Ulams\AuthoringMetrics\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Ulams\AuthoringMetrics\Enums\AuthoringMetricsPermissionsEnum;
use Ulams\Core\Enums\UserRole;

class AuthoringMetricsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (AuthoringMetricsPermissionsEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        Role::findOrCreate(UserRole::ADMIN, 'api')->givePermissionTo(AuthoringMetricsPermissionsEnum::getValues());
    }
}
