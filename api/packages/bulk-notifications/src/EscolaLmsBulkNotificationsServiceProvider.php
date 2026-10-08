<?php

namespace EscolaLms\BulkNotifications;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\BulkNotifications\Providers\SettingsServiceProvider;
use EscolaLms\BulkNotifications\Repositories\BulkNotificationRepository;
use EscolaLms\BulkNotifications\Repositories\Contracts\BulkNotificationRepositoryContract;
use EscolaLms\BulkNotifications\Repositories\Contracts\UserRepositoryContract;
use EscolaLms\BulkNotifications\Repositories\DeviceTokenRepository;
use EscolaLms\BulkNotifications\Repositories\Contracts\DeviceTokenRepositoryContract;
use EscolaLms\BulkNotifications\Repositories\UserRepository;
use EscolaLms\BulkNotifications\Services\BulkNotificationService;
use EscolaLms\BulkNotifications\Services\Contracts\BulkNotificationServiceContract;
use EscolaLms\BulkNotifications\Services\Contracts\DeviceTokenServiceContract;
use EscolaLms\BulkNotifications\Services\DeviceTokenService;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsBulkNotificationsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escolalms_bulk_notifications';

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
        $this->app->register(EscolaLmsSettingsServiceProvider::class);
        $this->app->register(EscolaLmsAuthServiceProvider::class);
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
