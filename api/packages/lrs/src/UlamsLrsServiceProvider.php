<?php

namespace Ulams\Lrs;

use Ulams\Lrs\Repositories\Contracts\StatementRepositoryContract;
use Ulams\Lrs\Repositories\StatementRepository;
use Ulams\Lrs\Services\Contracts\StatementServiceContract;
use Ulams\Lrs\Services\Contracts\XapiDocumentServiceContract;
use Ulams\Lrs\Services\Contracts\XapiStatementServiceContract;
use Ulams\Lrs\Services\StatementService;
use Illuminate\Support\ServiceProvider;
use Ulams\Lrs\Services\Contracts\LrsServiceContract;
use Ulams\Lrs\Services\LrsService;
use Ulams\Lrs\Services\XapiDocumentService;
use Ulams\Lrs\Services\XapiStatementService;

/**
 * SWAGGER_VERSION
 */
class UlamsLrsServiceProvider extends ServiceProvider
{
    public $singletons = [
        LrsServiceContract::class => LrsService::class,
        StatementServiceContract::class => StatementService::class,
        StatementRepositoryContract::class => StatementRepository::class,
        XapiStatementServiceContract::class => XapiStatementService::class,
        XapiDocumentServiceContract::class => XapiDocumentService::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
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
