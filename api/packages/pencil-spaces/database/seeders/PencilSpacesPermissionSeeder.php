<?php

namespace EscolaLms\PencilSpaces\Database\Seeders;

use EscolaLms\PencilSpaces\Enums\PencilSpacesPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PencilSpacesPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $student = Role::findOrCreate('student', 'api');
        $admin = Role::findOrCreate('admin', 'api');

        foreach (PencilSpacesPermissionEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo(PencilSpacesPermissionEnum::getValues());

        $student->givePermissionTo([
            PencilSpacesPermissionEnum::PENCIL_SPACES_LOGIN,
        ]);
    }
}
