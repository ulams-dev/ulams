<?php

namespace EscolaLms\Categories\Tests;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Categories\AuthServiceProvider;
use EscolaLms\Categories\EscolaLmsCategoriesServiceProvider;
use EscolaLms\Core\Models\User;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \EscolaLms\Core\Tests\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            EscolaLmsAuthServiceProvider::class,
            EscolaLmsCategoriesServiceProvider::class,
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
