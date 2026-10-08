<?php

namespace EscolaLms\Vouchers\Tests;

use EscolaLms\Cart\EscolaLmsCartServiceProvider;
use EscolaLms\Categories\EscolaLmsCategoriesServiceProvider;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\TestCase as CoreTestCase;
use EscolaLms\Payments\Providers\PaymentsServiceProvider;
use EscolaLms\Tags\EscolaLmsTagsServiceProvider;
use EscolaLms\Vouchers\EscolaLmsVouchersServiceProvider;
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
            EscolaLmsCategoriesServiceProvider::class,
            EscolaLmsTagsServiceProvider::class,
            EscolaLmsCartServiceProvider::class,
            EscolaLmsVouchersServiceProvider::class,
        ];
        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
