<?php

namespace EscolaLms\CourseAccess\Database\Seeders;

use EscolaLms\CourseAccess\Enum\CourseAccessPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CourseAccessPermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate('admin', 'api');
        $student = Role::findOrCreate('student', 'api');

        foreach (CourseAccessPermissionEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo(CourseAccessPermissionEnum::getValues());
        $student->givePermissionTo([
            CourseAccessPermissionEnum::CREATE_OWN_COURSE_ACCESS_ENQUIRY,
            CourseAccessPermissionEnum::DELETE_OWN_COURSE_ACCESS_ENQUIRY,
            CourseAccessPermissionEnum::LIST_OWN_COURSE_ACCESS_ENQUIRY,
        ]);
    }
}
