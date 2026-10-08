<?php

use Ulams\Core\Migrations\UlamsMigration;
use Ulams\Core\Seeders\RoleTableSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class InstallPassport extends UlamsMigration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // What `passport:install` did up to Passport 11. From Passport 12 the command also
        // publishes Passport's migrations (with new timestamps) and runs `migrate`, which would
        // duplicate the oauth tables that database/migrations already creates.
        Artisan::call('passport:keys');
        Artisan::call('passport:client', [
            '--personal' => true,
            '--name' => config('app.name') . ' Personal Access Client',
        ]);
        Artisan::call('passport:client', [
            '--password' => true,
            '--name' => config('app.name') . ' Password Grant Client',
            '--provider' => array_key_exists('users', config('auth.providers', [])) ? 'users' : null,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
