<?php

namespace Ulams\Bookmarks\Database\Seeders;

use Ulams\Bookmarks\Enums\BookmarkPermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BookmarkPermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::findOrCreate('admin', 'api');
        $student = Role::findOrCreate('student', 'api');

        foreach (BookmarkPermissionEnum::asArray() as $const => $value) {
            Permission::findOrCreate($value, 'api');
        }

        $admin->givePermissionTo(BookmarkPermissionEnum::adminPermissions());
        $student->givePermissionTo(BookmarkPermissionEnum::studentPermissions());
    }
}
