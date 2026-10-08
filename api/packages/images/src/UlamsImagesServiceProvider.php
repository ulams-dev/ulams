<?php

namespace Ulams\Images;

use Ulams\Core\UlamsServiceProvider;
use Ulams\Images\Console\ClearImagesCacheCommand;
use Ulams\Images\Enum\ConstantEnum;
use Ulams\Images\Enum\PackageStatusEnum;
use Ulams\Images\Providers\EventServiceProviders;
use Ulams\Images\Providers\SettingsServiceProvider;
use Ulams\Images\Repositories\Contracts\ImageCacheRepositoryContract;
use Ulams\Images\Repositories\ImageCacheRepository;
use Ulams\Images\Services\CustomFilesystemManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Ulams\Images\Services\Contracts\ImagesServiceContract;
use Ulams\Images\Services\ImagesService;

class UlamsImagesServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        ImagesServiceContract::class => ImagesService::class,
    ];

    public const REPOSITORIES = [
        ImageCacheRepositoryContract::class => ImageCacheRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'images');
        $this->app->register(EventServiceProviders::class);
        $this->app->register(SettingsServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
            $this->commands(ClearImagesCacheCommand::class);
        }
        $this->app->register(UlamsServiceProvider::class);

        $this->app->extend('filesystem', function ($service, $app) {
            return new CustomFilesystemManager($app);
        });

        RateLimiter::for('images.render', function (Request $request) {
            if (Config::get('images.private.rate_limiter_status') === PackageStatusEnum::ENABLED) {
                return [
                    Limit::perMinute(Config::get('images.private.rate_limit_global', ConstantEnum::RATE_LIMIT_GLOBAL)),
                    Limit::perMinute(Config::get('images.private.rate_limit_per_ip', ConstantEnum::RATE_LIMIT_PER_IP))->by($request->ip()),
                ];
            }

            return [];
        });
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('images.php'),
        ], 'images.config');
    }
}
