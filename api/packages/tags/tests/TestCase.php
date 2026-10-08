<?php

namespace EscolaLms\Tags\Tests;

use EscolaLms\Auth\Tests\Models\Client;
use EscolaLms\Tags\AuthServiceProvider;
use EscolaLms\Tags\EscolaLmsTagsServiceProvider;
use EscolaLms\Core\Models\User;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \EscolaLms\Core\Tests\TestCase
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
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            EscolaLmsTagsServiceProvider::class,
            AuthServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
