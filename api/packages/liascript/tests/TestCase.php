<?php

namespace Ulams\LiaScript\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Courses\Tests\Models\User;
use Ulams\LiaScript\Database\Seeders\LiaScriptPermissionSeeder;
use Ulams\LiaScript\UlamsLiaScriptServiceProvider;
use Ulams\Uploads\Tests\ZipFixtures;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;
    use ZipFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(LiaScriptPermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->cleanZipFixtures();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsLiaScriptServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
