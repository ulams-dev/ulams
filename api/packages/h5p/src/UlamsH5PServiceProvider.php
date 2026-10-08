<?php

namespace Ulams\H5P;

use Illuminate\Support\ServiceProvider;
use Ulams\H5P\Providers\AuthServiceProvider;
use Ulams\H5P\Services\Contracts\H5PContentServiceContract;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;
use Ulams\H5P\Services\H5PContentService;
use Ulams\H5P\Services\H5PServiceClient;

/**
 * SWAGGER_VERSION
 */
class UlamsH5PServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'h5p';

    public const SERVICES = [
        H5PServiceClientContract::class => H5PServiceClient::class,
        H5PContentServiceContract::class => H5PContentService::class,
    ];

    public $singletons = self::SERVICES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(AuthServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
            ], self::CONFIG_KEY . '.config');
        }
    }
}
