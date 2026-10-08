<?php

namespace Ulams\Lrs\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Lrs\UlamsLrsServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Lrs\Database\Seeders\LrsSeeder;
use Laravel\Passport\Passport;
use Ulams\Lrs\Tests\Models\Client;
use Ulams\Lrs\Tests\Models\User;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsLrsServiceProvider::class,
            UlamsCourseServiceProvider::class,
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
