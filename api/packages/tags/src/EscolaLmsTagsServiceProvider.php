<?php

namespace EscolaLms\Tags;

use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use EscolaLms\Tags\Repository\Contracts\TagRepositoryContract;
use EscolaLms\Tags\Repository\TagRepository;
use EscolaLms\Tags\Services\Contracts\TagServiceContract;
use EscolaLms\Tags\Services\TagService;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Middlewares\RoleMiddleware;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsTagsServiceProvider extends ServiceProvider
{
    public $singletons = [
        TagRepositoryContract::class => TagRepository::class,
        TagServiceContract::class => TagService::class
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'escolalms_tags');
        if (!app()->bound(EscolaLmsSettingsServiceProvider::class)) {
            $this->app->register(EscolaLmsSettingsServiceProvider::class);
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

        AdministrableConfig::registerConfig('escolalms_tags.morphable_classes');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'tags');
    }

    protected function bootForConsole()
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('escolalms_tags.php'),
        ], 'escolalms_tags.config');
    }


    private function loadMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations')
        ], 'escolalms');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
