<?php

namespace EscolaLms\Permissions\Tests;

use EscolaLms\Permissions\AuthServiceProvider;
use EscolaLms\Permissions\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Permissions\EscolaLmsPermissionsServiceProvider;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
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
            EscolaLmsPermissionsServiceProvider::class,
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            AuthServiceProvider::class
        ];
    }



    protected function authenticateAsAdmin()
    {
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
    }

    protected function findPermissionByName($responseData, $name)
    {
        $needle = array_filter($responseData->data, fn ($role) => $role->name === $name);
        if (count($needle) >= 1) {
            return array_pop($needle);
        }
        return false;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app->useEnvironmentPath(__DIR__ . '/..');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);
        parent::getEnvironmentSetUp($app);
    }
}
