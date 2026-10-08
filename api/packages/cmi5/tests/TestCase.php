<?php

namespace Ulams\Cmi5\Tests;

use Ulams\Cmi5\Tests\Models\Client;
use Ulams\Core\Models\User;
use Ulams\Cmi5\UlamsCmi5ServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Lrs\UlamsLrsServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;


class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsLrsServiceProvider::class,
            UlamsCmi5ServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
