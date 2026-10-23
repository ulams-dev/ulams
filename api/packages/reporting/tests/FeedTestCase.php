<?php

namespace Ulams\Reporting\Tests;

use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Ulams\Auth\Database\Seeders\AuthPermissionSeeder;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;

/** The reporting test case with the auth package loaded: scoped tokens, their limits and their route middleware. */
class FeedTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(AuthPermissionSeeder::class);
        Cache::flush();
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsAuthServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('auth.providers.users.model', User::class);
    }
}
