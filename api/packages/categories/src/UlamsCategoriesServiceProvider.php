<?php

namespace Ulams\Categories;

use Ulams\Categories\Commands\CategoriesSeedCommand;
use Ulams\Categories\Repositories\CategoriesRepository;
use Ulams\Categories\Repositories\Contracts\CategoriesRepositoryContract;
use Ulams\Categories\Services\CategoryService;
use Ulams\Categories\Services\Contracts\CategoryServiceContracts;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsCategoriesServiceProvider extends ServiceProvider
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
