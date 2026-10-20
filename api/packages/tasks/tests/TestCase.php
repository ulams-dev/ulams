<?php

namespace Ulams\Tasks\Tests;

use Ulams\Core\Support\MorphMap;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\Tasks\UlamsTasksServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        MorphMap::register(\Ulams\Courses\UlamsCourseServiceProvider::MORPH_ALIASES);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsTasksServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
