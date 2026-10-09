<?php

namespace Ulams\Interactive\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Interactive\Enums\InteractivePermissionsEnum;

class InteractivePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate(InteractivePermissionsEnum::INTERACTIVE_MANAGE, 'api');
        foreach (['admin', 'tutor'] as $role) {
            Role::findOrCreate($role, 'api')->givePermissionTo(InteractivePermissionsEnum::INTERACTIVE_MANAGE);
        }
    }
}
