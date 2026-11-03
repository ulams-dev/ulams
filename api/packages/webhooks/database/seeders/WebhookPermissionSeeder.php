<?php

namespace Ulams\Webhooks\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Webhooks\Enums\WebhookPermissionEnum;

class WebhookPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        foreach (WebhookPermissionEnum::asArray() as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
        $admin->givePermissionTo(WebhookPermissionEnum::adminPermissions());
    }
}
