<?php

namespace Ulams\CourseBuilder\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\CourseBuilder\Enums\CourseBuilderPermissionsEnum;

class CourseBuilderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate(CourseBuilderPermissionsEnum::COURSE_BUILDER_USE, 'api');
        foreach (['admin', 'tutor'] as $role) {
            Role::findOrCreate($role, 'api')->givePermissionTo(CourseBuilderPermissionsEnum::COURSE_BUILDER_USE);
        }
    }
}
