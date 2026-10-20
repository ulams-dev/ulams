<?php

namespace Ulams\Compliance\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Ulams\Compliance\Enums\CompliancePermissionsEnum;
use Ulams\Core\Enums\UserRole;

class CompliancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (CompliancePermissionsEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        Role::findOrCreate(UserRole::ADMIN, 'api')->givePermissionTo(CompliancePermissionsEnum::getValues());
        Role::findOrCreate(UserRole::TUTOR, 'api')->givePermissionTo(CompliancePermissionsEnum::COMPETENCIES);
    }
}
