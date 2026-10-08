<?php

namespace Ulams\Images\Tests;

use Ulams\Core\Models\User;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Ulams\Images\UlamsImagesServiceProvider;

class TestCase extends CoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsImagesServiceProvider::class,
            UlamsSettingsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
