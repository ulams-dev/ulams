<?php

namespace EscolaLms\Pages\Tests;

use EscolaLms\Core\Models\User;
use EscolaLms\Pages\AuthServiceProvider;
use EscolaLms\Pages\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Pages\Enums\PagesPermissionsEnum;
use EscolaLms\Pages\EscolaLmsPagesServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \EscolaLms\Core\Tests\TestCase
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
            EscolaLmsPagesServiceProvider::class,
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
