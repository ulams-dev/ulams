<?php

namespace Ulams\Cart\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\Cart\Models\User;
use Ulams\Cart\Providers\AuthServiceProvider;
use Ulams\Cart\Tests\Mocks\ExampleProductableMigration;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Payments\Providers\PaymentsServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            AuthServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            UlamsTagsServiceProvider::class,
            PaymentsServiceProvider::class,
            UlamsCartServiceProvider::class,
            UlamsTemplatesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', false);
        $app['config']->set('app.debug', env('APP_DEBUG', true));

        ExampleProductableMigration::run();
    }
}
