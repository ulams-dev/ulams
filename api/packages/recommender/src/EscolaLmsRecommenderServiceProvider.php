<?php

namespace EscolaLms\Recommender;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Recommender\Console\Commands\RetrySatisfactionJobCommand;
use EscolaLms\Recommender\Console\Commands\StartProcessingMeetFramesJobCommand;
use EscolaLms\Recommender\Http\Middleware\VerifySignature;
use EscolaLms\Recommender\Providers\AuthServiceProvider;
use EscolaLms\Recommender\Providers\EventServiceProvider;
use EscolaLms\Recommender\Providers\SettingsServiceProvider;
use EscolaLms\Recommender\Repositories\Contracts\TermAnalyticsRepositoryContract;
use EscolaLms\Recommender\Repositories\Contracts\TopicRepositoryContract;
use EscolaLms\Recommender\Repositories\TermAnalyticsRepository;
use EscolaLms\Recommender\Repositories\TopicRepository;
use EscolaLms\Recommender\Services\Contracts\RecommenderServiceContract;
use EscolaLms\Recommender\Services\Contracts\TermAnalyticServiceContract;
use EscolaLms\Recommender\Services\RecommenderService;
use EscolaLms\Recommender\Services\TermAnalyticService;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;

use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsRecommenderServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escolalms_recommender';

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
        $this->app->register(EscolaLmsSettingsServiceProvider::class);
        $this->app->register(EscolaLmsAuthServiceProvider::class);
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
