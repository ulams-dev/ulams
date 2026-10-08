<?php

namespace Ulams\Lrs;

use Ulams\Lrs\Repositories\Contracts\StatementRepositoryContract;
use Ulams\Lrs\Repositories\StatementRepository;
use Ulams\Lrs\Services\Contracts\StatementServiceContract;
use Ulams\Lrs\Services\StatementService;
use Illuminate\Support\ServiceProvider;
use Ulams\Lrs\Services\Contracts\LrsServiceContract;
use Ulams\Lrs\Services\LrsService;
use Illuminate\Support\Facades\Config;
use Ulams\Lrs\Extensions\AccessTokenGuard;
use \Trax\Core\TraxCoreServiceProvider;
use \Trax\Auth\AuthServiceProvider as TraxAuthServiceProvider;
use \Trax\XapiValidation\XapiValidationServiceProvider;
use \Trax\XapiStore\XapiStoreServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsLrsServiceProvider extends ServiceProvider
{
    public $singletons = [
        LrsServiceContract::class => LrsService::class,
        StatementServiceContract::class => StatementService::class,
        StatementRepositoryContract::class => StatementRepository::class,
    ];

    private $requiredProviders = [
        TraxCoreServiceProvider::class,
        TraxAuthServiceProvider::class,
        XapiValidationServiceProvider::class,
        XapiStoreServiceProvider::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        foreach ($this->requiredProviders as $provider) {
            if (!app()->bound($provider)) {
                $this->app->register($provider);
            }
        }

        Config::set(
            'trax-auth.app.guards.basic_http',
            AccessTokenGuard::class
        );

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'lrs');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
    }
}
