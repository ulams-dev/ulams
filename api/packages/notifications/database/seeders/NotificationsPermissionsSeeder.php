<?php

namespace EscolaLms\Notifications\Database\Seeders;

use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Notifications\Enums\NotificationsPermissionsEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class NotificationsPermissionsSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');

        Permission::findOrCreate(NotificationsPermissionsEnum::READ_ALL_NOTIFICATIONS, 'api');
        Permission::findOrCreate(NotificationsPermissionsEnum::READ_NOTIFICATION_EVENTS_LIST, 'api');

        $admin->givePermissionTo([NotificationsPermissionsEnum::READ_ALL_NOTIFICATIONS, NotificationsPermissionsEnum::READ_NOTIFICATION_EVENTS_LIST]);
    }
}
