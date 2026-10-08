<?php

namespace Ulams\Templates\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Core\Enums\UserRole;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    public $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsTemplatesServiceProvider::class,
            UlamsSettingsServiceProvider::class,
        ];
        if (class_exists(UlamsAuthServiceProvider::class)) {
            $providers[] = UlamsAuthServiceProvider::class;
        }
        if (class_exists(UlamsCategoriesServiceProvider::class)) {
            $providers[] = UlamsCategoriesServiceProvider::class;
        }
        return $providers;
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('mail.driver', 'log');
    }

    protected function authenticateAsAdmin(): void
    {
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole(UserRole::ADMIN);
    }
}
