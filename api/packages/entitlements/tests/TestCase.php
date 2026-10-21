<?php

namespace Ulams\Entitlements\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Courses\Models\User;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Entitlements\UlamsEntitlementsServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsEntitlementsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
