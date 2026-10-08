<?php

namespace EscolaLms\Payments\Database\Seeders;

use EscolaLms\Payments\Enums\PaymentsPermissionsEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PaymentsPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $admin = Role::findOrCreate('admin', 'api');

        Permission::findOrCreate(PaymentsPermissionsEnum::PAYMENTS_LIST, 'api');
        Permission::findOrCreate(PaymentsPermissionsEnum::PAYMENTS_READ, 'api');
        Permission::findOrCreate(PaymentsPermissionsEnum::PAYMENTS_EXPORT, 'api');

        $admin->givePermissionTo([
            PaymentsPermissionsEnum::PAYMENTS_LIST,
            PaymentsPermissionsEnum::PAYMENTS_READ,
            PaymentsPermissionsEnum::PAYMENTS_EXPORT,
        ]);
    }
}
