<?php

namespace Ulams\Scorm;

use Ulams\Scorm\Repositories\Contracts\ScormRepositoryContract;
use Ulams\Scorm\Repositories\ScormRepository;
use Ulams\Scorm\Services\Contracts\ScormQueryServiceContract;
use Ulams\Scorm\Services\Contracts\ScormServiceContract;
use Ulams\Scorm\Services\Contracts\ScormTrackServiceContract;
use Ulams\Scorm\Services\ScormQueryService;
use Ulams\Scorm\Services\ScormService;
use Ulams\Scorm\Services\ScormTrackService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsScormServiceProvider extends ServiceProvider
{
    public $singletons = [
        ScormServiceContract::class => ScormService::class,
        ScormQueryServiceContract::class => ScormQueryService::class,
        ScormTrackServiceContract::class => ScormTrackService::class,
        ScormRepositoryContract::class => ScormRepository::class,
    ];

    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'scorm');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/scorm'),
        ]);
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'scorm');
    }

    public function register(): void
    {

        $this->app->register(AuthServiceProvider::class);
    }
}
