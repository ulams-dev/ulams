<?php

namespace EscolaLms\StationaryEvents\Database\Seeders;

use EscolaLms\Core\Enums\UserRole;
use EscolaLms\StationaryEvents\Enum\StationaryEventPermissionsEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class StationaryEventPermissionSeeder extends Seeder
{
    public function run()
    {
        // create permissions
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');
        $tutor = Role::findOrCreate(UserRole::TUTOR, 'api');

        Permission::findOrCreate(StationaryEventPermissionsEnum::STATIONARY_EVENT_LIST, 'api');
        Permission::findOrCreate(StationaryEventPermissionsEnum::STATIONARY_EVENT_CREATE, 'api');
        Permission::findOrCreate(StationaryEventPermissionsEnum::STATIONARY_EVENT_READ, 'api');
        Permission::findOrCreate(StationaryEventPermissionsEnum::STATIONARY_EVENT_UPDATE, 'api');
        Permission::findOrCreate(StationaryEventPermissionsEnum::STATIONARY_EVENT_DELETE, 'api');

        $admin->givePermissionTo([
            StationaryEventPermissionsEnum::STATIONARY_EVENT_LIST,
            StationaryEventPermissionsEnum::STATIONARY_EVENT_CREATE,
            StationaryEventPermissionsEnum::STATIONARY_EVENT_READ,
            StationaryEventPermissionsEnum::STATIONARY_EVENT_UPDATE,
            StationaryEventPermissionsEnum::STATIONARY_EVENT_DELETE,
        ]);

        $tutor->givePermissionTo([
            StationaryEventPermissionsEnum::STATIONARY_EVENT_LIST,
            StationaryEventPermissionsEnum::STATIONARY_EVENT_CREATE,
            StationaryEventPermissionsEnum::STATIONARY_EVENT_READ,
            StationaryEventPermissionsEnum::STATIONARY_EVENT_UPDATE,
            StationaryEventPermissionsEnum::STATIONARY_EVENT_DELETE,
        ]);
    }
}
