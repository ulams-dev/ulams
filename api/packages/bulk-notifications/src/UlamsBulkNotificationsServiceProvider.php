<?php

namespace Ulams\BulkNotifications;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\BulkNotifications\Providers\SettingsServiceProvider;
use Ulams\BulkNotifications\Repositories\BulkNotificationRepository;
use Ulams\BulkNotifications\Repositories\Contracts\BulkNotificationRepositoryContract;
use Ulams\BulkNotifications\Repositories\Contracts\UserRepositoryContract;
use Ulams\BulkNotifications\Repositories\DeviceTokenRepository;
use Ulams\BulkNotifications\Repositories\Contracts\DeviceTokenRepositoryContract;
use Ulams\BulkNotifications\Repositories\UserRepository;
use Ulams\BulkNotifications\Services\BulkNotificationService;
use Ulams\BulkNotifications\Services\Contracts\BulkNotificationServiceContract;
use Ulams\BulkNotifications\Services\Contracts\DeviceTokenServiceContract;
use Ulams\BulkNotifications\Services\DeviceTokenService;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsBulkNotificationsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_bulk_notifications';

    public const REPOSITORIES = [
        DeviceTokenRepositoryContract::class => DeviceTokenRepository::class,
        BulkNotificationRepositoryContract::class => BulkNotificationRepository::class,
        UserRepositoryContract::class => UserRepository::class,
    ];

    public const SERVICES = [
        DeviceTokenServiceContract::class => DeviceTokenService::class,
        BulkNotificationServiceContract::class => BulkNotificationService::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(UlamsSettingsServiceProvider::class);
        $this->app->register(UlamsAuthServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
