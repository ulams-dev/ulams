<?php

namespace Ulams\Cart;

use Ulams\Cart\Console\Commands\AbandonedCart;
use Ulams\Cart\Providers\AuthServiceProvider;
use Ulams\Cart\Providers\EventServiceProvider;
use Ulams\Cart\Providers\ScheduleServiceProvider;
use Ulams\Cart\Providers\SettingsServiceProvider;
use Ulams\Cart\Services\Contracts\OrderServiceContract;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Services\Contracts\ShopServiceContract;
use Ulams\Cart\Services\OrderService;
use Ulams\Cart\Services\ProductService;
use Ulams\Cart\Services\ShopService;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Illuminate\Support\ServiceProvider;
use Treestoneit\ShoppingCart\CartServiceProvider as TreestoneitCartServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsCartServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_cart';

    public $singletons = [
        ProductServiceContract::class => ProductService::class,
        OrderServiceContract::class => OrderService::class,
        ShopServiceContract::class => ShopService::class,
    ];

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'cart');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'ulams_cart');

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(ScheduleServiceProvider::class);

        if (!$this->app->getProviders(UlamsTemplatesServiceProvider::class)) {
            $this->app->register(UlamsTemplatesServiceProvider::class);
        }
        if (!$this->app->getProviders(TreestoneitCartServiceProvider::class)) {
            $this->app->register(TreestoneitCartServiceProvider::class);
        }
    }

    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/config.php' => config_path('ulams_cart.php'),
        ], 'ulams_cart.config');

        $this->commands(AbandonedCart::class);
    }
}
