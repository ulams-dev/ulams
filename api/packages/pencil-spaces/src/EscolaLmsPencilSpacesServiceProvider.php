<?php

namespace EscolaLms\PencilSpaces;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\PencilSpaces\Providers\AuthServiceProvider;
use EscolaLms\PencilSpaces\Providers\SettingsServiceProvider;
use EscolaLms\PencilSpaces\Repositories\Contracts\UserRepositoryContract;
use EscolaLms\PencilSpaces\Repositories\UserRepository;
use EscolaLms\PencilSpaces\Services\Contracts\PencilSpacesServiceContract;
use EscolaLms\PencilSpaces\Services\PencilSpacesService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsPencilSpacesServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'pencil_spaces';

    public const SERVICES = [
        PencilSpacesServiceContract::class => PencilSpacesService::class,
    ];

    public const REPOSITORIES = [
        UserRepositoryContract::class => UserRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(EscolaLmsAuthServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', self::CONFIG_KEY);

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
