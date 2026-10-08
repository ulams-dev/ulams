<?php

namespace Ulams\Tasks;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Tasks\Providers\AuthServiceProvider;
use Ulams\Tasks\Providers\ScheduleServiceProvider;
use Ulams\Tasks\Providers\SettingsServiceProvider;
use Ulams\Tasks\Repositories\Contracts\TaskNoteRepositoryContract;
use Ulams\Tasks\Repositories\Contracts\TaskRepositoryContract;
use Ulams\Tasks\Repositories\TaskNoteRepository;
use Ulams\Tasks\Repositories\TaskRepository;
use Ulams\Tasks\Services\Contracts\TaskNoteServiceContract;
use Ulams\Tasks\Services\Contracts\TaskServiceContract;
use Ulams\Tasks\Services\TaskNoteService;
use Ulams\Tasks\Services\TaskService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsTasksServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_tasks';

    public const REPOSITORIES = [
        TaskRepositoryContract::class => TaskRepository::class,
        TaskNoteRepositoryContract::class => TaskNoteRepository::class,
    ];

    public const SERVICES = [
        TaskServiceContract::class => TaskService::class,
        TaskNoteServiceContract::class => TaskNoteService::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(ScheduleServiceProvider::class);
        $this->app->register(UlamsSettingsServiceProvider::class);
        $this->app->register(UlamsAuthServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
