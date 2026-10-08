<?php

namespace EscolaLms\BulkNotifications\Database\Seeders;

use EscolaLms\BulkNotifications\Enums\BulkNotificationPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BulkNotificationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        $tutor = Role::findOrCreate('tutor', 'api');
        $student = Role::findOrCreate('student', 'api');

        foreach (BulkNotificationPermissionEnum::asArray() as $const => $value) {
            Permission::findOrCreate($value, 'api');
        }

        $admin->givePermissionTo(BulkNotificationPermissionEnum::asArray());
        $tutor->givePermissionTo([BulkNotificationPermissionEnum::CREATE_DEVICE_TOKEN]);
        $student->givePermissionTo([BulkNotificationPermissionEnum::CREATE_DEVICE_TOKEN]);
    }
}
