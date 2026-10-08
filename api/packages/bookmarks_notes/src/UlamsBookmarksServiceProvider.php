<?php

namespace Ulams\Bookmarks;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Bookmarks\Providers\AuthServiceProvider;
use Ulams\Bookmarks\Repositories\BookmarkRepository;
use Ulams\Bookmarks\Repositories\Contracts\BookmarkRepositoryContract;
use Ulams\Bookmarks\Services\BookmarkService;
use Ulams\Bookmarks\Services\Contracts\BookmarkServiceContract;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsBookmarksServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_bookmarks';

    public const REPOSITORIES = [
        BookmarkRepositoryContract::class => BookmarkRepository::class
    ];

    public const SERVICES = [
        BookmarkServiceContract::class => BookmarkService::class
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(AuthServiceProvider::class);
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
