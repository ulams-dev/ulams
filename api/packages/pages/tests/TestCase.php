<?php

namespace Ulams\Pages\Tests;

use Ulams\Core\Models\User;
use Ulams\Pages\AuthServiceProvider;
use Ulams\Pages\Database\Seeders\PermissionTableSeeder;
use Ulams\Pages\Enums\PagesPermissionsEnum;
use Ulams\Pages\UlamsPagesServiceProvider;
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
        return [
            ...parent::getPackageProviders($app),
            UlamsPagesServiceProvider::class,
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            AuthServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
    }

    protected function authenticateAsAdmin(): void
    {
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->givePermissionTo(PagesPermissionsEnum::PAGE_LIST);
        $this->user->givePermissionTo(PagesPermissionsEnum::PAGE_READ);
        $this->user->givePermissionTo(PagesPermissionsEnum::PAGE_CREATE);
        $this->user->givePermissionTo(PagesPermissionsEnum::PAGE_UPDATE);
        $this->user->givePermissionTo(PagesPermissionsEnum::PAGE_DELETE);
    }
}
