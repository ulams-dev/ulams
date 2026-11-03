<?php

namespace Ulams\StationaryEvents;

use Ulams\Core\Support\MorphMap;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\StationaryEvents\Providers\AuthServiceProvider;
use Ulams\StationaryEvents\Repositories\Contracts\StationaryEventRepositoryContract;
use Ulams\StationaryEvents\Repositories\StationaryEventRepository;
use Ulams\StationaryEvents\Services\Contracts\StationaryEventServiceContract;
use Ulams\StationaryEvents\Services\StationaryEventService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsStationaryEventsServiceProvider extends ServiceProvider
{
    /** Morph aliases of the models of this package (see Ulams\Core\Support\MorphMap); tests of packages that only use the models register them too. */
    public const MORPH_ALIASES = [
        'stationary_event' => \Ulams\StationaryEvents\Models\StationaryEvent::class,
    ];

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
        MorphMap::register(self::MORPH_ALIASES);

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(UlamsCategoriesServiceProvider::class);
    }
}
