<?php

namespace Ulams\Vouchers;

use Ulams\Cart\Services\Contracts\OrderServiceContract as CartOrderServiceContract;
use Ulams\Cart\Services\Contracts\ShopServiceContract as CartShopServiceContract;
use Ulams\Vouchers\Providers\AuthServiceProvider;
use Ulams\Vouchers\Services\Contracts\CouponServiceContract;
use Ulams\Vouchers\Services\Contracts\OrderServiceContract;
use Ulams\Vouchers\Services\Contracts\ShopServiceContract;
use Ulams\Vouchers\Services\CouponService;
use Ulams\Vouchers\Services\OrderService;
use Ulams\Vouchers\Services\ShopService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsVouchersServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_vouchers';

    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        CouponServiceContract::class => CouponService::class,
        OrderServiceContract::class => OrderService::class,
        ShopServiceContract::class => ShopService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->extend(CartOrderServiceContract::class, function ($service, $app) {
            return app(OrderServiceContract::class);
        });
        $this->app->extend(CartShopServiceContract::class, function ($service, $app) {
            return app(ShopServiceContract::class);
        });

        $this->app->register(AuthServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'coupon');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        if (class_exists(\Ulams\Settings\Facades\AdministrableConfig::class)) {
        }
    }

    public function bootForConsole(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
