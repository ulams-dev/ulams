<?php

namespace EscolaLms\CsvUsers\Database\Seeders;

use EscolaLms\Core\Enums\UserRole;
use EscolaLms\CsvUsers\Enums\CsvUserPermissionsEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CsvUsersPermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');

        Permission::findOrCreate(CsvUserPermissionsEnum::CSV_USERS_EXPORT, 'api');
        Permission::findOrCreate(CsvUserPermissionsEnum::CSV_USERS_IMPORT, 'api');
        Permission::findOrCreate(CsvUserPermissionsEnum::CSV_USER_GROUP_EXPORT, 'api');
        Permission::findOrCreate(CsvUserPermissionsEnum::CSV_USER_GROUP_IMPORT, 'api');

        $admin->givePermissionTo([
            CsvUserPermissionsEnum::CSV_USERS_EXPORT,
            CsvUserPermissionsEnum::CSV_USERS_IMPORT,
            CsvUserPermissionsEnum::CSV_USER_GROUP_EXPORT,
            CsvUserPermissionsEnum::CSV_USER_GROUP_IMPORT,
        ]);
    }
}
