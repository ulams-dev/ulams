<?php

namespace Ulams\Notifications;

use Ulams\Notifications\Listeners\NotifiableEventListener;
use Ulams\Notifications\Models\DatabaseNotification;
use Illuminate\Notifications\Channels\DatabaseChannel as IlluminateDatabaseChannel;
use Ulams\Notifications\Core\DatabaseChannel;
use Ulams\Notifications\Services\Contracts\DatabaseNotificationsServiceContract;
use Ulams\Notifications\Services\DatabaseNotificationsService;
use Illuminate\Notifications\DatabaseNotification as IlluminateDatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsNotificationsServiceProvider extends ServiceProvider
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

        Event::listen('Ulams*', function ($eventName, array $data) {
            (new NotifiableEventListener())->handle($data[0]);
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'ulams_notifications');
        $this->app->singleton(IlluminateDatabaseChannel::class, DatabaseChannel::class);
        $this->app->instance(IlluminateDatabaseNotification::class, new DatabaseNotification());
    }

    protected function bootForConsole(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
