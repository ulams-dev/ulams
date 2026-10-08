<?php

namespace EscolaLms\Dictionaries;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Categories\EscolaLmsCategoriesServiceProvider;
use EscolaLms\Dictionaries\Providers\AuthServiceProvider;
use EscolaLms\Dictionaries\Repositories\CategoryRepository;
use EscolaLms\Dictionaries\Repositories\Contracts\CategoryRepositoryContract;
use EscolaLms\Dictionaries\Repositories\Contracts\DictionaryRepositoryContract;
use EscolaLms\Dictionaries\Repositories\Contracts\DictionaryUserRepositoryContract;
use EscolaLms\Dictionaries\Repositories\Contracts\DictionaryWordRepositoryContract;
use EscolaLms\Dictionaries\Repositories\DictionaryRepository;
use EscolaLms\Dictionaries\Repositories\DictionaryUserRepository;
use EscolaLms\Dictionaries\Repositories\DictionaryWordRepository;
use EscolaLms\Dictionaries\Services\Contracts\DictionaryAccessServiceContract;
use EscolaLms\Dictionaries\Services\Contracts\DictionaryServiceContract;
use EscolaLms\Dictionaries\Services\Contracts\DictionaryWordServiceContract;
use EscolaLms\Dictionaries\Services\DictionaryAccessService;
use EscolaLms\Dictionaries\Services\DictionaryService;
use EscolaLms\Dictionaries\Services\DictionaryWordService;
use Illuminate\Support\ServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsDictionariesServiceProvider extends ServiceProvider
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
        $this->app->register(EscolaLmsAuthServiceProvider::class);
        $this->app->register(EscolaLmsCategoriesServiceProvider::class);
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
