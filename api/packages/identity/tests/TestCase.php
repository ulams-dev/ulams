<?php

namespace Ulams\Identity\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\Database\Seeders\AuthPermissionSeeder;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\Models\Client;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Http\SafeHttp;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Identity\Database\Seeders\IdentityPermissionSeeder;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Tests\Support\FakeIdp;
use Ulams\Identity\UlamsIdentityServiceProvider;
use Ulams\ModelFields\ModelFieldsServiceProvider;
use Ulams\Templates\UlamsTemplatesServiceProvider;

class TestCase extends CoreTestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    protected FakeIdp $idp;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(AuthPermissionSeeder::class);
        $this->seed(IdentityPermissionSeeder::class);
        Role::findOrCreate(UserRole::ADMIN, 'api');
        Role::findOrCreate(UserRole::STUDENT, 'api');
        Role::findOrCreate(UserRole::TUTOR, 'api');
        Cache::flush();

        // the provider is "on the internet" and answers from memory
        SafeHttp::useResolver(fn (string $host) => ['93.184.216.34']);
        $this->idp = new FakeIdp();
        $this->app->instance('identity.http_handler', $this->idp->handler());
        config([
            'app.frontend_url' => 'https://app.example.test',
            'ulams.core.security.trusted_origins' => ['https://app.example.test', 'https://admin.example.test'],
        ]);
    }

    protected function tearDown(): void
    {
        SafeHttp::useResolver(null);
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            ...parent::getPackageProviders($app),
            UlamsAuthServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            ModelFieldsServiceProvider::class,
            UlamsIdentityServiceProvider::class,
        ];
        if (class_exists(UlamsTemplatesServiceProvider::class)) {
            $providers[] = UlamsTemplatesServiceProvider::class;
        }

        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('cache.default', 'array');
    }

    protected function student(array $attributes = []): User
    {
        return $this->makeStudent($attributes);
    }

    protected function tutor(array $attributes = []): User
    {
        return $this->makeInstructor($attributes);
    }

    protected function admin(array $attributes = []): User
    {
        return $this->makeAdmin($attributes);
    }

    /** An enabled OIDC provider pointed at the fake IdP. */
    protected function provider(array $attributes = []): IdentityProvider
    {
        $oidc = array_replace([
            'issuer' => FakeIdp::ISSUER,
            'client_id' => FakeIdp::CLIENT_ID,
            'scopes' => ['email', 'profile'],
            'token_auth' => 'client_secret_basic',
            'userinfo' => false,
        ], $attributes['oidc'] ?? []);
        unset($attributes['oidc']);

        $provider = new IdentityProvider();
        $provider->forceFill($attributes + [
            'slug' => 'acme',
            'name' => 'Acme staff',
            'protocol' => 'oidc',
            'enabled' => true,
            'email_domains' => ['example.com'],
            'trust_email' => false,
            'jit_enabled' => true,
            'default_role' => 'student',
            'oidc' => $oidc,
            'oidc_client_secret' => FakeIdp::CLIENT_SECRET,
            'oidc_secret_hint' => substr(FakeIdp::CLIENT_SECRET, -4),
        ]);
        $provider->save();

        return $provider->refresh();
    }
}
