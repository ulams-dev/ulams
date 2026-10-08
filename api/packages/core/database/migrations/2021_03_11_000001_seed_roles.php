<?php

use Ulams\Core\Migrations\UlamsMigration;
use Ulams\Core\Seeders\RoleTableSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class SeedRoles extends UlamsMigration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        app(RoleTableSeeder::class)->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Role::truncate();
    }
}
