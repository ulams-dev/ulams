<?php

namespace EscolaLms\StationaryEvents;

use EscolaLms\Categories\EscolaLmsCategoriesServiceProvider;
use EscolaLms\StationaryEvents\Providers\AuthServiceProvider;
use EscolaLms\StationaryEvents\Repositories\Contracts\StationaryEventRepositoryContract;
use EscolaLms\StationaryEvents\Repositories\StationaryEventRepository;
use EscolaLms\StationaryEvents\Services\Contracts\StationaryEventServiceContract;
use EscolaLms\StationaryEvents\Services\StationaryEventService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsStationaryEventsServiceProvider extends ServiceProvider
{
    public const SERVICES = [
      StationaryEventServiceContract::class => StationaryEventService::class,
    ];

    public const REPOSITORIES = [
      StationaryEventRepositoryContract::class => StationaryEventRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'stationary-event');
    }

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(EscolaLmsCategoriesServiceProvider::class);
    }
}
