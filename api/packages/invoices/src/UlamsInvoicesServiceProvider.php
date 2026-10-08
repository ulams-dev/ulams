<?php

namespace Ulams\Invoices;

use Ulams\Invoices\Providers\SettingsServiceProvider;
use Ulams\Invoices\Services\Contracts\InvoicesServiceContract;
use Ulams\Invoices\Services\InvoicesService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsInvoicesServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'invoices';

    public $bindings = [
        InvoicesServiceContract::class => InvoicesService::class,
    ];

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'invoices');
        $this->loadJsonTranslationsFrom(__DIR__ . '/resources/lang');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function register(): void
    {
        parent::register();
        $this->app->register(SettingsServiceProvider::class);

        $this->mergeConfigFrom(__DIR__ . '/../config/invoices.php', self::CONFIG_KEY);
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/../config/invoices.php' => config_path('invoices.php'),
        ], 'ulams_invoices.config');
    }
}
