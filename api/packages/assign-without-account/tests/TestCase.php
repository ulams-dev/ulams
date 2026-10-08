<?php

namespace EscolaLms\AssignWithoutAccount\Tests;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\AssignWithoutAccount\EscolaLmsAssignWithoutAccountServiceProvider;
use EscolaLms\Cart\EscolaLmsCartServiceProvider;
use EscolaLms\Cart\Tests\Mocks\ExampleProductableMigration;
use EscolaLms\Auth\Models\User;
use EscolaLms\Templates\EscolaLmsTemplatesServiceProvider;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \EscolaLms\Core\Tests\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            EscolaLmsAuthServiceProvider::class,
            EscolaLmsCartServiceProvider::class,
            EscolaLmsTemplatesServiceProvider::class,
            EscolaLmsAssignWithoutAccountServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);

        ExampleProductableMigration::run();
    }
}
