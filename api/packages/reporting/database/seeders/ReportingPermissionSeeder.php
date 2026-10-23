<?php

namespace Ulams\Reporting\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Ulams\Core\Enums\UserRole;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;

class ReportingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (ReportingPermissionsEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        Role::findOrCreate(UserRole::ADMIN, 'api')->givePermissionTo(ReportingPermissionsEnum::getValues());
        Role::findOrCreate(UserRole::TUTOR, 'api')->givePermissionTo(ReportingPermissionsEnum::READ);
    }
}
