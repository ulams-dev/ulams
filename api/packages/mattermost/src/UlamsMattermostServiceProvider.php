<?php

namespace Ulams\Mattermost;

use Ulams\Mattermost\Providers\EventServiceProvider;
use Ulams\Mattermost\Providers\SettingsServiceProvider;
use Illuminate\Support\ServiceProvider;
use Ulams\Mattermost\Services\Contracts\MattermostServiceContract;
use Ulams\Mattermost\Services\MattermostService;
use Ulams\Mattermost\Support\MattermostManager;

/**
 * SWAGGER_VERSION
 */

class UlamsMattermostServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        MattermostServiceContract::class => MattermostService::class,
        MattermostManager::class => MattermostManager::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mattermost.php',
            'mattermost'
        );

        $this->app->register(SettingsServiceProvider::class)->booted(function () {
            $this->app->register(EventServiceProvider::class);
        });
    }
}
