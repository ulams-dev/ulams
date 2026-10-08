<?php

namespace Ulams\BulkNotifications\Tests;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Models\User;
use Ulams\BulkNotifications\Channels\PushNotificationChannel;
use Ulams\BulkNotifications\UlamsBulkNotificationsServiceProvider;
use Ulams\Core\Tests\TestCase as CoreTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsBulkNotificationsServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }

    protected static function channelDataProvider(): array
    {
        return [
            ['channel' => PushNotificationChannel::class,]
        ];
    }
}
