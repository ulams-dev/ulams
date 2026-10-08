<?php

namespace EscolaLms\MailerLite\Tests;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Auth\Tests\Models\Client;
use EscolaLms\MailerLite\EscolaLmsMailerLiteServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use Laravel\Passport\Passport;
use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Tests\TestCase as CoreTestCase;

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
            EscolaLmsMailerLiteServiceProvider::class,
            EscolaLmsAuthServiceProvider::class,
            EscolaLmsSettingsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('escolalms_mailer_lite.api_key', 'fc7b8c5b');
    }
}
