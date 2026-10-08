<?php

namespace EscolaLms\Video\Database\Seeders;

use EscolaLms\Video\Enums\VideoPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VideoPermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate('admin', 'api');

        foreach (VideoPermissionEnum::asArray() as $const => $value) {
            Permission::findOrCreate($value, 'api');
        }

        $admin->givePermissionTo([
            VideoPermissionEnum::VIDEO_PROCESS_STATES_LIST,
        ]);
    }
}
