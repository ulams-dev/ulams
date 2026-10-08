<?php

namespace EscolaLms\Tasks;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Tasks\Providers\AuthServiceProvider;
use EscolaLms\Tasks\Providers\ScheduleServiceProvider;
use EscolaLms\Tasks\Providers\SettingsServiceProvider;
use EscolaLms\Tasks\Repositories\Contracts\TaskNoteRepositoryContract;
use EscolaLms\Tasks\Repositories\Contracts\TaskRepositoryContract;
use EscolaLms\Tasks\Repositories\TaskNoteRepository;
use EscolaLms\Tasks\Repositories\TaskRepository;
use EscolaLms\Tasks\Services\Contracts\TaskNoteServiceContract;
use EscolaLms\Tasks\Services\Contracts\TaskServiceContract;
use EscolaLms\Tasks\Services\TaskNoteService;
use EscolaLms\Tasks\Services\TaskService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsTasksServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escolalms_tasks';

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
        $this->app->register(EscolaLmsSettingsServiceProvider::class);
        $this->app->register(EscolaLmsAuthServiceProvider::class);
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
