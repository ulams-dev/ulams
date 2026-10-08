<?php

namespace EscolaLms\Mattermost;

use EscolaLms\Mattermost\Providers\EventServiceProvider;
use EscolaLms\Mattermost\Providers\SettingsServiceProvider;
use Illuminate\Support\ServiceProvider;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Services\MattermostService;

/**
 * SWAGGER_VERSION
 */

class EscolaLmsMattermostServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        MattermostServiceContract::class => MattermostService::class,
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
