<?php

namespace Ulams\Tenancy\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Tenancy\UlamsTenancyServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('tenants')) {
            (require __DIR__ . '/../database/migrations/2026_10_08_000000_create_tenants_table.php')->up();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsSettingsServiceProvider::class,
            UlamsTenancyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('auth.providers.users.model', \Ulams\Auth\Models\User::class);
        $app['config']->set('ulams_tenancy.tenant_slug', null);
    }
}
