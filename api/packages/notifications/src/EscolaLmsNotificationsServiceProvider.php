<?php

namespace EscolaLms\Notifications;

use EscolaLms\Notifications\Listeners\NotifiableEventListener;
use EscolaLms\Notifications\Models\DatabaseNotification;
use Illuminate\Notifications\Channels\DatabaseChannel as IlluminateDatabaseChannel;
use EscolaLms\Notifications\Core\DatabaseChannel;
use EscolaLms\Notifications\Services\Contracts\DatabaseNotificationsServiceContract;
use EscolaLms\Notifications\Services\DatabaseNotificationsService;
use Illuminate\Notifications\DatabaseNotification as IlluminateDatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsNotificationsServiceProvider extends ServiceProvider
{
    public $singletons = [
        DatabaseNotificationsServiceContract::class => DatabaseNotificationsService::class
    ];

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'dashboard-app');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        Event::listen('EscolaLms*', function ($eventName, array $data) {
            (new NotifiableEventListener())->handle($data[0]);
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'escolalms_notifications');
        $this->app->singleton(IlluminateDatabaseChannel::class, DatabaseChannel::class);
        $this->app->instance(IlluminateDatabaseNotification::class, new DatabaseNotification());
    }

    protected function bootForConsole(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
