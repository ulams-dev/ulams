<?php

namespace EscolaLms\Pages;

use EscolaLms\Pages\Http\Services\Contracts\PageServiceContract;
use EscolaLms\Pages\Http\Services\PageService;
use EscolaLms\Pages\Repository\Contracts\PageRepositoryContract;
use EscolaLms\Pages\Repository\PageRepository;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsPagesServiceProvider extends ServiceProvider
{
    public $singletons = [
        PageRepositoryContract::class => PageRepository::class,
        PageServiceContract::class => PageService::class,
    ];

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'pages-migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders' => database_path('seeders'),
        ], 'pages-seeders');

        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if (!config('escolalms.tags.ignore_migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'page');
    }
}
