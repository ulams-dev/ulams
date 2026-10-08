<?php

namespace Ulams\Auth\Tests;

use Ulams\Auth\Database\Seeders\AuthPermissionSeeder;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\ModelFields\ModelFieldsServiceProvider;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(AuthPermissionSeeder::class);
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            ModelFieldsServiceProvider::class,
        ];

        if (class_exists(UlamsTemplatesServiceProvider::class)) {
            array_push($providers, UlamsTemplatesServiceProvider::class);
        }

        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
