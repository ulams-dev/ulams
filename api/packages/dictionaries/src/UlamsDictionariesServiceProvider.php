<?php

namespace Ulams\Dictionaries;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Dictionaries\Providers\AuthServiceProvider;
use Ulams\Dictionaries\Repositories\CategoryRepository;
use Ulams\Dictionaries\Repositories\Contracts\CategoryRepositoryContract;
use Ulams\Dictionaries\Repositories\Contracts\DictionaryRepositoryContract;
use Ulams\Dictionaries\Repositories\Contracts\DictionaryUserRepositoryContract;
use Ulams\Dictionaries\Repositories\Contracts\DictionaryWordRepositoryContract;
use Ulams\Dictionaries\Repositories\DictionaryRepository;
use Ulams\Dictionaries\Repositories\DictionaryUserRepository;
use Ulams\Dictionaries\Repositories\DictionaryWordRepository;
use Ulams\Dictionaries\Services\Contracts\DictionaryAccessServiceContract;
use Ulams\Dictionaries\Services\Contracts\DictionaryServiceContract;
use Ulams\Dictionaries\Services\Contracts\DictionaryWordServiceContract;
use Ulams\Dictionaries\Services\DictionaryAccessService;
use Ulams\Dictionaries\Services\DictionaryService;
use Ulams\Dictionaries\Services\DictionaryWordService;
use Illuminate\Support\ServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsDictionariesServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        DictionaryServiceContract::class => DictionaryService::class,
        DictionaryWordServiceContract::class => DictionaryWordService::class,
        DictionaryAccessServiceContract::class => DictionaryAccessService::class,
    ];

    public const REPOSITORIES = [
        CategoryRepositoryContract::class => CategoryRepository::class,
        DictionaryRepositoryContract::class => DictionaryRepository::class,
        DictionaryWordRepositoryContract::class => DictionaryWordRepository::class,
        DictionaryUserRepositoryContract::class => DictionaryUserRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register(): void
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(UlamsAuthServiceProvider::class);
        $this->app->register(UlamsCategoriesServiceProvider::class);
        $this->app->register(ExcelServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole(): void
    {
    }
}
