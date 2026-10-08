<?php

namespace Ulams\Youtube\Tests;

use Ulams\Youtube\UlamsYoutubeServiceProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use GuzzleHttp\Psr7\Response;
use Ulams\Core\Models\User;


class TestCase extends CoreTestCase
{
    use DatabaseTransactions;
    protected MockHandler $mock;


    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {

        return [
            ...parent::getPackageProviders($app),
            UlamsYoutubeServiceProvider::class,
            UlamsSettingsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);

        $this->mock = new MockHandler([new Response(200, ['Token' => 'Token'], 'Hello, World'),]);
        $handlerStack = HandlerStack::create($this->mock);
    }
}
