<?php

namespace Ulams\CourseVariants\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Ulams\Core\Enums\UserRole;
use Ulams\CourseVariants\Enums\CourseVariantsPermissionsEnum;

class CourseVariantsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (CourseVariantsPermissionsEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        Role::findOrCreate(UserRole::ADMIN, 'api')->givePermissionTo(CourseVariantsPermissionsEnum::getValues());
    }
}
