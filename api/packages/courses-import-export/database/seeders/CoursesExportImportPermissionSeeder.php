<?php

namespace EscolaLms\CoursesImportExport\Database\Seeders;

use EscolaLms\Core\Enums\UserRole;
use EscolaLms\CoursesImportExport\Enums\CoursesImportExportPermissionsEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CoursesExportImportPermissionSeeder extends Seeder
{
    public function run()
    {
        // create permissions
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');

        Permission::findOrCreate(CoursesImportExportPermissionsEnum::COURSES_EXPORT, 'api');
        Permission::findOrCreate(CoursesImportExportPermissionsEnum::COURSES_IMPORT, 'api');
        Permission::findOrCreate(CoursesImportExportPermissionsEnum::COURSES_EXPORT_OWNED, 'api');
        Permission::findOrCreate(CoursesImportExportPermissionsEnum::COURSES_CLONE, 'api');

        $admin->givePermissionTo([
            CoursesImportExportPermissionsEnum::COURSES_EXPORT,
            CoursesImportExportPermissionsEnum::COURSES_IMPORT,
            CoursesImportExportPermissionsEnum::COURSES_EXPORT_OWNED,
            CoursesImportExportPermissionsEnum::COURSES_CLONE,
        ]);
    }
}
