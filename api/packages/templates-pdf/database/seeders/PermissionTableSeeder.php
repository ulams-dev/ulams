<?php

namespace Ulams\TemplatesPdf\Database\Seeders;

use Ulams\Core\Enums\UserRole;
use Ulams\TemplatesPdf\Enums\PdfPermissionsEnum;
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
            PdfPermissionsEnum::PDF_READ_ALL,
            PdfPermissionsEnum::PDF_LIST,

        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $apiAdmin->givePermissionTo($permissions);
    }
}
