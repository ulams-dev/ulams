<?php

namespace Ulams\ModelFields\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\ModelFields\ModelFieldsServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\ModelFields\Database\Seeders\PermissionTableSeeder;
use Ulams\ModelFields\Tests\TestModelFieldsServiceProvider;


class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
    }

    protected function getPackageProviders($app): array
    {

        return [
            ...parent::getPackageProviders($app),
            ModelFieldsServiceProvider::class,
            TestModelFieldsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
