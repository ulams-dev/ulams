<?php

namespace EscolaLms\Categories;

use EscolaLms\Categories\Commands\CategoriesSeedCommand;
use EscolaLms\Categories\Repositories\CategoriesRepository;
use EscolaLms\Categories\Repositories\Contracts\CategoriesRepositoryContract;
use EscolaLms\Categories\Services\CategoryService;
use EscolaLms\Categories\Services\Contracts\CategoryServiceContracts;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsCategoriesServiceProvider extends ServiceProvider
{
    public $singletons = [
        CategoriesRepositoryContract::class => CategoriesRepository::class,
        CategoryServiceContracts::class => CategoryService::class
    ];

    public function register() : void
    {
        $this->commands([CategoriesSeedCommand::class]);
    }

    public function boot() : void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'category');
        $this->app['router']->aliasMiddleware('role', \Spatie\Permission\Middlewares\RoleMiddleware::class);
    }
}
