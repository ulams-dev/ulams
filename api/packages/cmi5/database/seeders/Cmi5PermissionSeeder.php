<?php

namespace EscolaLms\Cmi5\Database\Seeders;

use EscolaLms\Cmi5\Enums\Cmi5PermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Cmi5PermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate('admin', 'api');

        foreach (Cmi5PermissionEnum::asArray() as $const => $value) {
            Permission::findOrCreate($value, 'api');
        }

        $admin->givePermissionTo([
            Cmi5PermissionEnum::CMI5_UPLOAD,
            Cmi5PermissionEnum::CMI5_LIST,
            Cmi5PermissionEnum::CMI5_READ,
        ]);
    }
}
