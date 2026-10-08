<?php

namespace Ulams\Invoices\Tests;

use Barryvdh\DomPDF\ServiceProvider;
use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\Cart\Tests\Mocks\ExampleProductableMigration;
use Ulams\Core\Models\User;
use Ulams\Invoices\UlamsInvoicesServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsInvoicesServiceProvider::class,
            UlamsCartServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('app.debug', env('APP_DEBUG', true));

        ExampleProductableMigration::run();
    }
}
