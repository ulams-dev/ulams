<?php

namespace Ulams\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Tenancy\Enums\TenancyPermissionsEnum;
use Ulams\Tenancy\Support\TenantContext;

/**
 * `tenancy_manage` exists everywhere but belongs to the platform's admin role only. In a tenant it is
 * granted to a person on purpose (a platform operator who builds courses), never by role.
 */
class TenancyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate(TenancyPermissionsEnum::TENANCY_MANAGE, 'api');
        if (TenantContext::isPlatform()) {
            Role::findOrCreate('admin', 'api')->givePermissionTo(TenancyPermissionsEnum::TENANCY_MANAGE);
        }
    }
}
