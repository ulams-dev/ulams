<?php

namespace Ulams\LiaScript\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\LiaScript\Enums\LiaScriptPermissionsEnum;

class LiaScriptPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate(LiaScriptPermissionsEnum::LIASCRIPT_MANAGE, 'api');
        foreach (['admin', 'tutor'] as $role) {
            Role::findOrCreate($role, 'api')->givePermissionTo(LiaScriptPermissionsEnum::LIASCRIPT_MANAGE);
        }
    }
}
