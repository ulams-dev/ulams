<?php

namespace Ulams\CommerceSylius;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Services\ProductLinkStore;
use Ulams\Commerce\UlamsCommerceServiceProvider;
use Ulams\CommerceSylius\Http\SyliusClient;

/**
 * The Sylius adapter of the commerce package (ADR 0131). It only registers the provider under the key `sylius`;
 * `COMMERCE_PROVIDER=sylius` selects it, and without `COMMERCE_SYLIUS_URL` it is never built.
 */
class UlamsCommerceSyliusServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'commerce-sylius';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsCommerceServiceProvider::class);
        $this->app->bind(SyliusClient::class, fn () => SyliusClient::fromConfig());
        $this->callAfterResolving(CommerceManager::class, function (CommerceManager $manager): void {
            $manager->extend(SyliusProvider::KEY, fn (Container $c) => new SyliusProvider($c->make(SyliusClient::class), $c->make(ProductLinkStore::class)));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php')], self::CONFIG_KEY . '.config');
        }
    }
}
