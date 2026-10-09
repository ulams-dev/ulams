<?php

namespace Ulams\Commerce;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Providers\WellmsCartProvider;

/**
 * `CommerceProvider` before Sylius (ADR 0049): the LMS talks to commerce through one interface; the
 * Wellms cart is the default adapter.
 */
class UlamsCommerceServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'commerce';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsCartServiceProvider::class);

        $this->app->singleton(CommerceManager::class, function (Container $app) {
            $manager = new CommerceManager($app);
            $manager->extend(WellmsCartProvider::KEY, fn (Container $c) => new WellmsCartProvider($c->make(ProductServiceContract::class)));

            return $manager;
        });
        $this->app->bind(CommerceProvider::class, fn (Container $app) => $app->make(CommerceManager::class)->provider());
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php')], self::CONFIG_KEY . '.config');
        }
    }
}
