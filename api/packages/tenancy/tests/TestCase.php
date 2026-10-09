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
        // idempotent: adds tenants.demo to a test database migrated before it existed
        (require __DIR__ . '/../database/migrations/2026_10_09_000000_add_demo_to_tenants_table.php')->up();
        (require __DIR__ . '/../database/migrations/2026_10_10_000000_add_env_overrides_to_tenants_table.php')->up();
        (require __DIR__ . '/../database/migrations/2026_10_11_000000_create_tenant_upgrade_steps_table.php')->up();
        (require __DIR__ . '/../database/migrations/2026_10_12_000000_create_tenant_operations_table.php')->up();
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
