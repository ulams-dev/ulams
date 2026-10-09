<?php

namespace Ulams\Cmi5;

use Ulams\Cmi5\Console\MoveToBucketCommand;
use Ulams\Cmi5\Providers\AuthServiceProvider;
use Ulams\Cmi5\Repositories\Cmi5AuRepository;
use Ulams\Cmi5\Repositories\Cmi5Repository;
use Ulams\Cmi5\Repositories\Contracts\Cmi5AuRepositoryContract;
use Ulams\Cmi5\Repositories\Contracts\Cmi5RepositoryContract;
use Ulams\Cmi5\Services\Cmi5Service;
use Ulams\Cmi5\Services\Cmi5UploadService;
use Ulams\Cmi5\Services\Contracts\Cmi5ServiceContract;
use Ulams\Cmi5\Services\Contracts\Cmi5UploadServiceContract;
use Illuminate\Support\ServiceProvider;
use Ulams\Uploads\UlamsUploadsServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsCmi5ServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_cmi5';

    const SERVICES = [
        Cmi5ServiceContract::class => Cmi5Service::class,
        Cmi5UploadServiceContract::class => Cmi5UploadService::class,
    ];

    const REPOSITORIES = [
        Cmi5RepositoryContract::class => Cmi5Repository::class,
        Cmi5AuRepositoryContract::class => Cmi5AuRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(UlamsUploadsServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cmi5');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'cmi5');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole()
    {
        $this->commands([MoveToBucketCommand::class]);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
