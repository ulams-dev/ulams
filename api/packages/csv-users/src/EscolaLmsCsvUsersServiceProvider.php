<?php

namespace EscolaLms\CsvUsers;

use EscolaLms\CsvUsers\Services\Contracts\CsvUserGroupServiceContract;
use EscolaLms\CsvUsers\Services\Contracts\CsvUserServiceContract;
use EscolaLms\CsvUsers\Services\CsvUserGroupService;
use EscolaLms\CsvUsers\Services\CsvUserService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsCsvUsersServiceProvider extends ServiceProvider
{
    public $singletons = [
        CsvUserServiceContract::class => CsvUserService::class,
        CsvUserGroupServiceContract::class => CsvUserGroupService::class,
    ];

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'csv-users');
    }

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
    }
}
