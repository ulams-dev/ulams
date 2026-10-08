<?php

namespace EscolaLms\Payments\Providers;

use EscolaLms\Payments\Gateway\Drivers\Contracts\GatewayDriverContract;
use EscolaLms\Payments\Gateway\GatewayManager;
use EscolaLms\Payments\Repositories\Contracts\PaymentsRepositoryContract;
use EscolaLms\Payments\Repositories\PaymentsRepository;
use EscolaLms\Payments\Services\Contracts\PaymentsServiceContract;
use EscolaLms\Payments\Services\PaymentsService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class PaymentsServiceProvider extends ServiceProvider
{
    public $singletons = [
        PaymentsRepositoryContract::class => PaymentsRepository::class,
        PaymentsServiceContract::class => PaymentsService::class
    ];

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'payment');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config.php', 'escolalms_payments');

        $this->app->singleton('payments', function ($app) {
            return app(PaymentsServiceContract::class);
        });
        $this->app->singleton('payment-gateway', function ($app) {
            return new GatewayManager($app);
        });
        $this->app->singleton('payment-gateway.driver', function ($app) {
            return $app['payment-gateway']->driver();
        });
        $this->app->alias('payment-gateway.driver', GatewayDriverContract::class);

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'payments',
            'payment-gateway'
        ];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/../config.php' => config_path('escolalms_payments.php'),
        ], 'escolalms_payments.config');

        // Publishing the database migrations.
        $this->publishes([
            __DIR__ . '/../../database/migrations' => $this->app->databasePath('migrations'),
        ], 'escolalms_payments.migrations');
    }
}
