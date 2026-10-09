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
        // On Passport 13 both `passport:client` calls ask for a user provider when none is given,
        // so it is always passed. The clients are created on the Passport 12 columns here and
        // converted by 2026_10_08_140000_upgrade_oauth_clients_to_passport_13.
        $provider = array_key_exists('users', config('auth.providers', [])) ? 'users' : null;
        Artisan::call('passport:keys');
        Artisan::call('passport:client', [
            '--personal' => true,
            '--name' => config('app.name') . ' Personal Access Client',
            '--provider' => $provider,
            '--no-interaction' => true,
        ]);
        Artisan::call('passport:client', [
            '--password' => true,
            '--name' => config('app.name') . ' Password Grant Client',
            '--provider' => $provider,
            '--no-interaction' => true,
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
