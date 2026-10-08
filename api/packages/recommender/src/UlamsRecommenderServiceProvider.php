<?php

namespace Ulams\Recommender;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Recommender\Console\Commands\RetrySatisfactionJobCommand;
use Ulams\Recommender\Console\Commands\StartProcessingMeetFramesJobCommand;
use Ulams\Recommender\Http\Middleware\VerifySignature;
use Ulams\Recommender\Providers\AuthServiceProvider;
use Ulams\Recommender\Providers\EventServiceProvider;
use Ulams\Recommender\Providers\SettingsServiceProvider;
use Ulams\Recommender\Repositories\Contracts\TermAnalyticsRepositoryContract;
use Ulams\Recommender\Repositories\Contracts\TopicRepositoryContract;
use Ulams\Recommender\Repositories\TermAnalyticsRepository;
use Ulams\Recommender\Repositories\TopicRepository;
use Ulams\Recommender\Services\Contracts\RecommenderServiceContract;
use Ulams\Recommender\Services\Contracts\TermAnalyticServiceContract;
use Ulams\Recommender\Services\RecommenderService;
use Ulams\Recommender\Services\TermAnalyticService;
use Ulams\Settings\UlamsSettingsServiceProvider;

use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsRecommenderServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_recommender';

    public const REPOSITORIES = [
        TopicRepositoryContract::class => TopicRepository::class,
        TermAnalyticsRepositoryContract::class => TermAnalyticsRepository::class,
    ];

    public const SERVICES = [
        RecommenderServiceContract::class => RecommenderService::class,
        TermAnalyticServiceContract::class => TermAnalyticService::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(UlamsSettingsServiceProvider::class);
        $this->app->register(UlamsAuthServiceProvider::class);
    }

    public function boot()
    {
        $router = $this->app->get('router');
        $router->aliasMiddleware('verifySignature', VerifySignature::class);

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole()
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');

        $this->commands([
            RetrySatisfactionJobCommand::class,
            StartProcessingMeetFramesJobCommand::class,
        ]);
    }
}
