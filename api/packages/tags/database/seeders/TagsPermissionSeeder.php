<?php

namespace EscolaLms\Tags\Database\Seeders;

use EscolaLms\Tags\Enums\TagsPermissionsEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class TagsPermissionSeeder extends Seeder
{
    public function run()
    {
        // create permissions
        $admin = Role::findOrCreate('admin', 'api');
        $tutor = Role::findOrCreate('tutor', 'api');

        $permissions = [
            TagsPermissionsEnum::TAGS_CREATE,
            TagsPermissionsEnum::TAGS_UPDATE,
            TagsPermissionsEnum::TAGS_DELETE,
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo($permissions);
        $tutor->givePermissionTo($permissions);
    }
}
