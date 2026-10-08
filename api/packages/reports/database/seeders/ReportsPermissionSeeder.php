<?php

namespace Ulams\Reports\Database\Seeders;

use Ulams\Reports\Enums\ReportsPermissionsEnum;
use Ulams\Core\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ReportsPermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');

        foreach (ReportsPermissionsEnum::asArray() as $const => $value) {
            Permission::findOrCreate($value, 'api');
        }

        $admin->givePermissionTo([
            ReportsPermissionsEnum::DISPLAY_REPORTS
        ]);
    }
}
