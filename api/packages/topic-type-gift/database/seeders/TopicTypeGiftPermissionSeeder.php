<?php

namespace Ulams\TopicTypeGift\Database\Seeders;

use Ulams\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TopicTypeGiftPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        $student = Role::findOrCreate('student', 'api');
        $tutor = Role::findOrCreate('tutor', 'api');

        foreach (TopicTypeGiftPermissionEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo(TopicTypeGiftPermissionEnum::getValues());
        $student->givePermissionTo(TopicTypeGiftPermissionEnum::studentPermissions());
        $tutor->givePermissionTo(TopicTypeGiftPermissionEnum::tutorPermissions());
    }
}
