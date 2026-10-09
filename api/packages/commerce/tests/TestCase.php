<?php

namespace Ulams\Commerce\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Cart\Models\User;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Cart\Tests\Mocks\ExampleProductableMigration;
use Ulams\Commerce\UlamsCommerceServiceProvider;
use Ulams\Payments\Providers\PaymentsServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PaymentsServiceProvider::class,
            UlamsCommerceServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('commerce.sellables.course', ExampleProductable::class);
        $app['config']->set('commerce.front_url', 'https://shop.test');
        ExampleProductableMigration::run();
    }
}
