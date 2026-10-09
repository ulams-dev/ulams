<?php

namespace Ulams\LivingCourse\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\LivingCourse\Enums\LivingCoursePermissionsEnum;

class LivingCoursePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate(LivingCoursePermissionsEnum::LIVING_COURSE_REVIEW, 'api');
        Role::findOrCreate('admin', 'api')->givePermissionTo(LivingCoursePermissionsEnum::LIVING_COURSE_REVIEW);
    }
}
