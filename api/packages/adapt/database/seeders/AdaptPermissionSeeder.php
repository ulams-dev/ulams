<?php

namespace Ulams\Adapt\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdaptPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate('adapt_manage', 'api');
        foreach (['admin', 'tutor'] as $role) {
            Role::findOrCreate($role, 'api')->givePermissionTo('adapt_manage');
        }
    }
}
