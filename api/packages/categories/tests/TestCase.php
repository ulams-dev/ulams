<?php

namespace Ulams\Categories\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Categories\AuthServiceProvider;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Core\Models\User;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            AuthServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('app.debug', env('APP_DEBUG', true));
    }
}
