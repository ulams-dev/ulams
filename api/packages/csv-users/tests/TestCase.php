<?php

namespace Ulams\CsvUsers\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\CsvUsers\AuthServiceProvider;
use Ulams\CsvUsers\Database\Seeders\CsvUsersPermissionSeeder;
use Ulams\CsvUsers\UlamsCsvUsersServiceProvider;
use Ulams\CsvUsers\Models\User as UserTest;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    protected $response;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(CsvUsersPermissionSeeder::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsCsvUsersServiceProvider::class,
            AuthServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', UserTest::class);
    }

    public function assertApiSuccess()
    {
        $this->response->assertJson(['success' => true]);
    }
}
