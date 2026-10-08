<?php

namespace EscolaLms\ConsultationAccess\Database\Seeders;

use EscolaLms\ConsultationAccess\Enum\ConsultationAccessPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ConsultationAccessPermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate('admin', 'api');
        $student = Role::findOrCreate('student', 'api');

        foreach (ConsultationAccessPermissionEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin->givePermissionTo(ConsultationAccessPermissionEnum::getValues());

        $student->givePermissionTo([
            ConsultationAccessPermissionEnum::LIST_OWN_CONSULTATION_ACCESS_ENQUIRY,
            ConsultationAccessPermissionEnum::READ_OWN_CONSULTATION_ACCESS_ENQUIRY,
            ConsultationAccessPermissionEnum::CREATE_OWN_CONSULTATION_ACCESS_ENQUIRY,
            ConsultationAccessPermissionEnum::DELETE_OWN_CONSULTATION_ACCESS_ENQUIRY,
            ConsultationAccessPermissionEnum::UPDATE_OWN_CONSULTATION_ACCESS_ENQUIRY,
        ]);
    }
}
