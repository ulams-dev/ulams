<?php

namespace Ulams\Translations\Database\Seeders;

use Ulams\Core\Enums\UserRole;
use Ulams\Translations\Enum\TranslationsPermissionsEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class TranslationsPermissionSeeder extends Seeder
{
    public function run()
    {
        foreach (TranslationsPermissionsEnum::getValues() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');
        $admin->givePermissionTo(TranslationsPermissionsEnum::getValues());
        $tutor = Role::findOrCreate(UserRole::TUTOR, 'api');
        $tutor->givePermissionTo(TranslationsPermissionsEnum::TRANSLATION_LIST_READONLY);
    }
}
