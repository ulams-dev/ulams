<?php

namespace Ulams\KnowledgeExport\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\KnowledgeExport\Enums\KnowledgePermissionEnum;

class KnowledgePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        foreach (KnowledgePermissionEnum::asArray() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        $admin->givePermissionTo(KnowledgePermissionEnum::adminPermissions());
    }
}
