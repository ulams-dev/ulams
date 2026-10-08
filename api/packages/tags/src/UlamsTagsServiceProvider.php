<?php

namespace Ulams\Tags;

use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Tags\Repository\Contracts\TagRepositoryContract;
use Ulams\Tags\Repository\TagRepository;
use Ulams\Tags\Services\Contracts\TagServiceContract;
use Ulams\Tags\Services\TagService;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Middlewares\RoleMiddleware;

/**
 * SWAGGER_VERSION
 */
class UlamsTagsServiceProvider extends ServiceProvider
{
    public $singletons = [
        TagRepositoryContract::class => TagRepository::class,
        TagServiceContract::class => TagService::class
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'ulams_tags');
        if (!app()->bound(UlamsSettingsServiceProvider::class)) {
            $this->app->register(UlamsSettingsServiceProvider::class);
        }
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrations();
        $this->app['router']->aliasMiddleware('role', RoleMiddleware::class);
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        AdministrableConfig::registerConfig('ulams_tags.morphable_classes');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'tags');
    }

    protected function bootForConsole()
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('ulams_tags.php'),
        ], 'ulams_tags.config');
    }


    private function loadMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations')
        ], 'ulams');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
