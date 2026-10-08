<?php

namespace Ulams\HeadlessH5P\Tests;

use Ulams\Core\UlamsServiceProvider;
use Ulams\Core\Models\User;
use Ulams\HeadlessH5P\Database\Seeders\PermissionTableSeeder;
use Ulams\HeadlessH5P\Tests\Models\Client;
use Ulams\Settings\UlamsSettingsServiceProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Ulams\HeadlessH5P\HeadlessH5PServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    public $user;

    protected MockHandler $mock;

    protected $container = [];

    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(PermissionTableSeeder::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            HeadlessH5PServiceProvider::class,
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            UlamsServiceProvider::class,
            UlamsSettingsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->mock = new MockHandler([new Response(200, [], 'Hello, World')]);
        $handlerStack = HandlerStack::create($this->mock);

        // Setup default database to use sqlite :memory:
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('hh5p.guzzle', ['handler' => $handlerStack]);
        $app['config']->set('hh5p.h5p_export', true);
    }

    protected function authenticateAsAdmin(): void
    {
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
    }

    protected function authenticateAsUser(): void
    {
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
    }
}
