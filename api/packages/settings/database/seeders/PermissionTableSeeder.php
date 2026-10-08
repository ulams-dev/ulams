<?php

namespace EscolaLms\Settings\Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Settings\Enums\SettingsPermissionsEnum;
use Illuminate\Database\Seeder;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        // create permissions
        $admin = Role::findOrCreate(UserRole::ADMIN, 'api');

        Permission::findOrCreate(SettingsPermissionsEnum::SETTINGS_MANAGE, 'api');
        //
        Permission::findOrCreate(SettingsPermissionsEnum::SETTINGS_CREATE, 'api');
        Permission::findOrCreate(SettingsPermissionsEnum::SETTINGS_DELETE, 'api');
        Permission::findOrCreate(SettingsPermissionsEnum::SETTINGS_UPDATE, 'api');
        Permission::findOrCreate(SettingsPermissionsEnum::SETTINGS_READ, 'api');
        Permission::findOrCreate(SettingsPermissionsEnum::SETTINGS_LIST, 'api');
        Permission::findOrCreate(SettingsPermissionsEnum::CONFIG_LIST, 'api');
        Permission::findOrCreate(SettingsPermissionsEnum::CONFIG_UPDATE, 'api');
        Permission::findOrCreate(SettingsPermissionsEnum::SETTINGS_LIST_READONLY, 'api');

        $admin->givePermissionTo([
            SettingsPermissionsEnum::SETTINGS_MANAGE,
            SettingsPermissionsEnum::SETTINGS_CREATE,
            SettingsPermissionsEnum::SETTINGS_DELETE,
            SettingsPermissionsEnum::SETTINGS_UPDATE,
            SettingsPermissionsEnum::SETTINGS_READ,
            SettingsPermissionsEnum::SETTINGS_LIST,
            SettingsPermissionsEnum::CONFIG_LIST,
            SettingsPermissionsEnum::CONFIG_UPDATE,
        ]);

        $tutor = Role::findOrCreate(UserRole::TUTOR, 'api');
        $tutor->givePermissionTo([
            SettingsPermissionsEnum::SETTINGS_LIST_READONLY,
        ]);
    }
}
