<?php

namespace Ulams\MailerLite\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Tests\Models\Client;
use Ulams\MailerLite\UlamsMailerLiteServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Laravel\Passport\Passport;
use Ulams\Auth\Models\User;
use Ulams\Core\Tests\TestCase as CoreTestCase;

class TestCase extends CoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsMailerLiteServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsSettingsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('ulams_mailer_lite.api_key', 'fc7b8c5b');
    }
}
