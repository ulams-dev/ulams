<?php

namespace EscolaLms\Vouchers\Database\Seeders;

use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Vouchers\Enums\VoucherPermissionsEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use Illuminate\Database\Seeder;

class VoucherPermissionsSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');
        $tutor = Role::findOrCreate(UserRole::TUTOR, 'api');
        $student = Role::findOrCreate(UserRole::STUDENT, 'api');

        Permission::findOrCreate(VoucherPermissionsEnum::COUPON_LIST, 'api');
        Permission::findOrCreate(VoucherPermissionsEnum::COUPON_CREATE, 'api');
        Permission::findOrCreate(VoucherPermissionsEnum::COUPON_READ, 'api');
        Permission::findOrCreate(VoucherPermissionsEnum::COUPON_UPDATE, 'api');
        Permission::findOrCreate(VoucherPermissionsEnum::COUPON_DELETE, 'api');
        Permission::findOrCreate(VoucherPermissionsEnum::COUPON_USE, 'api');

        $admin->givePermissionTo([
            VoucherPermissionsEnum::COUPON_LIST,
            VoucherPermissionsEnum::COUPON_CREATE,
            VoucherPermissionsEnum::COUPON_READ,
            VoucherPermissionsEnum::COUPON_UPDATE,
            VoucherPermissionsEnum::COUPON_DELETE,
            VoucherPermissionsEnum::COUPON_USE,
        ]);
        $tutor->givePermissionTo([
            VoucherPermissionsEnum::COUPON_USE,
        ]);
        $student->givePermissionTo([
            VoucherPermissionsEnum::COUPON_USE,
        ]);
    }
}
