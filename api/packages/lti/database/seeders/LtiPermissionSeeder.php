<?php

namespace Ulams\Lti\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Lti\Enums\LtiPermissionsEnum;

class LtiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        Permission::findOrCreate(LtiPermissionsEnum::LTI_MANAGE, 'api');
        $admin->givePermissionTo(LtiPermissionsEnum::LTI_MANAGE);
    }
}
