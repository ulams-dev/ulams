<?php

namespace Ulams\LearnerInsights\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\LearnerInsights\Enums\LearnerInsightsPermissionsEnum;

class LearnerInsightsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate('admin', 'api');
        foreach (LearnerInsightsPermissionsEnum::all() as $permission) {
            Permission::findOrCreate($permission, 'api');
            $admin->givePermissionTo($permission);
        }
    }
}
