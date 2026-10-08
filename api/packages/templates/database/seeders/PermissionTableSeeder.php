<?php

namespace EscolaLms\Templates\Database\Seeders;

use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Templates\Enums\TemplatesPermissionsEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @todo remove neccesity of using 'web' guard
 */
class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $apiAdmin = Role::findOrCreate(UserRole::ADMIN, 'api');
        $permissions = [
            TemplatesPermissionsEnum::TEMPLATES_CREATE,
            TemplatesPermissionsEnum::TEMPLATES_DELETE,
            TemplatesPermissionsEnum::TEMPLATES_UPDATE,
            TemplatesPermissionsEnum::TEMPLATES_LIST,
            TemplatesPermissionsEnum::TEMPLATES_READ,
            TemplatesPermissionsEnum::EVENTS_TRIGGER,
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $apiAdmin->givePermissionTo($permissions);
    }
}
