<?php

namespace Ulams\Auth;

use Ulams\Auth\Console\Commands\CreateAdminCommand;
use Ulams\Auth\Providers\AuthServiceProvider;
use Ulams\Auth\Providers\EventServiceProvider;
use Ulams\Auth\Providers\SettingsServiceProvider;
use Ulams\Auth\Repositories\Contracts\PreUserRepositoryContract;
use Ulams\Auth\Repositories\Contracts\SocialAccountRepositoryContract;
use Ulams\Auth\Repositories\Contracts\UserGroupRepositoryContract;
use Ulams\Auth\Repositories\Contracts\UserRepositoryContract;
use Ulams\Auth\Repositories\PreUserRepository;
use Ulams\Auth\Repositories\SocialAccountRepository;
use Ulams\Auth\Repositories\UserGroupRepository;
use Ulams\Auth\Repositories\UserRepository;
use Ulams\Auth\Services\AuthService;
use Ulams\Auth\Services\Contracts\AuthServiceContract;
use Ulams\Auth\Services\Contracts\SocialAccountServiceContract;
use Ulams\Auth\Services\Contracts\UserGroupServiceContract;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Auth\Services\SocialAccountService;
use Ulams\Auth\Services\UserGroupService;
use Ulams\Auth\Services\UserService;
use Ulams\ModelFields\ModelFieldsServiceProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\SocialiteServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsAuthServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_auth';

    public const SERVICES = [
        AuthServiceContract::class => AuthService::class,
        UserGroupServiceContract::class => UserGroupService::class,
        UserServiceContract::class => UserService::class,
        SocialAccountServiceContract::class => SocialAccountService::class,
    ];

    public const REPOSITORIES = [
        UserGroupRepositoryContract::class => UserGroupRepository::class,
        UserRepositoryContract::class => UserRepository::class,
        PreUserRepositoryContract::class => PreUserRepository::class,
        SocialAccountRepositoryContract::class => SocialAccountRepository::class,
    ];

    public array $bindings = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'auth');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/auth'),
        ]);
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'user');

        $this->app->register(EventServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(ModelFieldsServiceProvider::class);
        $this->app->register(SocialiteServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
        $this->commands([
            CreateAdminCommand::class
        ]);
    }
}
