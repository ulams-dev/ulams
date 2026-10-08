<?php

namespace EscolaLms\TopicTypeProject\Database\Seeders;

use EscolaLms\TopicTypeProject\Enum\TopicTypeProjectPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TopicTypeProjectPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        $student = Role::findOrCreate('student', 'api');

        foreach (TopicTypeProjectPermissionEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo(TopicTypeProjectPermissionEnum::getValues());

        $student->givePermissionTo([
            TopicTypeProjectPermissionEnum::CREATE_OWN_PROJECT_SOLUTION,
            TopicTypeProjectPermissionEnum::LIST_OWN_PROJECT_SOLUTION,
            TopicTypeProjectPermissionEnum::DELETE_OWN_PROJECT_SOLUTION,
            TopicTypeProjectPermissionEnum::READ_OWN_PROJECT_SOLUTION,
        ]);
    }
}
