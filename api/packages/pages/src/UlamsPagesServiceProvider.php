<?php

namespace Ulams\Pages;

use Ulams\Pages\Http\Services\Contracts\PageServiceContract;
use Ulams\Pages\Http\Services\PageService;
use Ulams\Pages\Repository\Contracts\PageRepositoryContract;
use Ulams\Pages\Repository\PageRepository;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsPagesServiceProvider extends ServiceProvider
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

        if (!config('ulams.tags.ignore_migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'page');
    }
}
