<?php

namespace EscolaLms\Bookmarks;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Bookmarks\Providers\AuthServiceProvider;
use EscolaLms\Bookmarks\Repositories\BookmarkRepository;
use EscolaLms\Bookmarks\Repositories\Contracts\BookmarkRepositoryContract;
use EscolaLms\Bookmarks\Services\BookmarkService;
use EscolaLms\Bookmarks\Services\Contracts\BookmarkServiceContract;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsBookmarksServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escolalms_bookmarks';

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
