<?php

namespace Ulams\Jitsi\Tests;



use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Jitsi\Enum\PackageStatusEnum;
use Ulams\ModelFields\ModelFieldsServiceProvider;
use Ulams\Core\UlamsServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use Ulams\Jitsi\UlamsJitsiServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;

use Laravel\Passport\Passport;
use Ulams\Lrs\Tests\Models\Client;
use Ulams\Auth\Models\User;

use Ulams\Core\Tests\TestCase as CoreTestCase;

// use GuzzleHttp\Client;


class TestCase extends CoreTestCase
{
    use DatabaseTransactions;


    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app): array
    {

        return [
            ...parent::getPackageProviders($app),
            UlamsJitsiServiceProvider::class,
            UlamsSettingsServiceProvider::class,
            UlamsAuthServiceProvider::class,
            ModelFieldsServiceProvider::class,
            UlamsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);

        $app['config']->set('jitsi.app_id', 'app_id');
        $app['config']->set('jitsi.secret', 'secret');
        $app['config']->set('jitsi.jitsi_host', 'localhost');
        $app['config']->set('jitsi.package_status', PackageStatusEnum::ENABLED);

        $app['config']->set('jitsi.jaas_host', 'localhost');
        $app['config']->set('jitsi.aud', 'jitsi');
        $app['config']->set('jitsi.iss', 'chat');
        $app['config']->set('jitsi.sub', '');
        $app['config']->set('jitsi.kid', '');
    }
}
