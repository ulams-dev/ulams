<?php

namespace Ulams\CsvUsers;

use Ulams\CsvUsers\Services\Contracts\CsvUserGroupServiceContract;
use Ulams\CsvUsers\Services\Contracts\CsvUserServiceContract;
use Ulams\CsvUsers\Services\CsvUserGroupService;
use Ulams\CsvUsers\Services\CsvUserService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsCsvUsersServiceProvider extends ServiceProvider
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
