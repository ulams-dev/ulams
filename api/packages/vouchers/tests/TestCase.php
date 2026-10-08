<?php

namespace Ulams\Vouchers\Tests;

use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Core\Models\User;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Payments\Providers\PaymentsServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use Ulams\Vouchers\UlamsVouchersServiceProvider;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ...parent::getPackageProviders($app),
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            PaymentsServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            UlamsTagsServiceProvider::class,
            UlamsCartServiceProvider::class,
            UlamsVouchersServiceProvider::class,
        ];
        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
