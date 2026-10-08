<?php

namespace EscolaLms\AssignWithoutAccount;

use EscolaLms\AssignWithoutAccount\Providers\EventServiceProvider;
use EscolaLms\AssignWithoutAccount\Repositories\Contracts\UserSubmissionRepositoryContract;
use EscolaLms\AssignWithoutAccount\Repositories\UserSubmissionRepository;
use EscolaLms\AssignWithoutAccount\Services\Contracts\UserSubmissionServiceContract;
use EscolaLms\AssignWithoutAccount\Services\UserSubmissionService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsAssignWithoutAccountServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escolalms_assign_without_account';

    public $singletons = [
        UserSubmissionRepositoryContract::class => UserSubmissionRepository::class,
        UserSubmissionServiceContract::class => UserSubmissionService::class
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'assign-without-account');

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
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
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
