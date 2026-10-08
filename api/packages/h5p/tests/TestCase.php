<?php

namespace Ulams\H5P\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\H5P\UlamsH5PServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsH5PServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('h5p.service_url', 'http://h5p.test:8080');
        $app['config']->set('h5p.internal_token', 'test-internal-token');
    }
}
