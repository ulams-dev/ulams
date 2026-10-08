<?php

namespace Ulams\Webinar;

use Ulams\Webinar\Providers\EventServiceProvider;
use Ulams\Jitsi\UlamsJitsiServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Webinar\Enum\WebinarTermReminderStatusEnum;
use Ulams\Webinar\Jobs\ReminderAboutWebinarJob;
use Ulams\Webinar\Repositories\Contracts\WebinarRepositoryContract;
use Ulams\Webinar\Repositories\WebinarRepository;
use Ulams\Webinar\Services\Contracts\WebinarServiceContract;
use Ulams\Webinar\Services\WebinarService;
use Ulams\Youtube\UlamsYoutubeServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsWebinarServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        WebinarServiceContract::class => WebinarService::class
    ];
    public const REPOSITORIES = [
        WebinarRepositoryContract::class => WebinarRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'webinar');
        $this->loadRoutesFrom(__DIR__ . '/channels.php');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('ulams_webinar.php'),
        ], 'ulams_webinar');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'ulams_webinar');
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(UlamsJitsiServiceProvider::class);
        $this->app->register(UlamsSettingsServiceProvider::class);
        $this->app->register(UlamsYoutubeServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
    }
}
