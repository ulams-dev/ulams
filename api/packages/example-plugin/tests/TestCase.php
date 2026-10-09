<?php

namespace Ulams\ExamplePlugin\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\ExamplePlugin\Database\Seeders\ExamplePluginPermissionSeeder;
use Ulams\ExamplePlugin\UlamsExamplePluginServiceProvider;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder as SettingsPermissionSeeder;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Spatie\Permission\Models\Role;

/**
 * The provider is registered here, not in config/app.php: the package stays out of the
 * application until you enable it (README.md).
 */
class TestCase extends CoreTestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        Role::findOrCreate(UserRole::STUDENT, 'api');
        $this->seed(ExamplePluginPermissionSeeder::class);
        $this->seed(SettingsPermissionSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsSettingsServiceProvider::class,
            UlamsExamplePluginServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        // the settings package caches the config: keep it in memory, away from a shared Redis
        $app['config']->set('cache.default', 'array');
        $app['config']->set(UlamsExamplePluginServiceProvider::CONFIG_KEY . '.greeting', 'Hello from the tests');
    }
}
