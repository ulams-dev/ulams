<?php

namespace Ulams\AssignWithoutAccount\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\AssignWithoutAccount\UlamsAssignWithoutAccountServiceProvider;
use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\Cart\Tests\Mocks\ExampleProductableMigration;
use Ulams\Auth\Models\User;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsCartServiceProvider::class,
            UlamsTemplatesServiceProvider::class,
            UlamsAssignWithoutAccountServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);

        ExampleProductableMigration::run();
    }
}
