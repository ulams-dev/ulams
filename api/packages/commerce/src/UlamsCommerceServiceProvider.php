<?php

namespace Ulams\Commerce;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\Cart\Events\ProductAttached;
use Ulams\Cart\Events\ProductBought;
use Ulams\Commerce\Console\BackfillAccessCommand;
use Ulams\Commerce\Console\ReconcileCommand;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Listeners\RecordWellmsPurchase;
use Ulams\Commerce\Providers\WellmsCartProvider;
use Ulams\Entitlements\UlamsEntitlementsServiceProvider;

/**
 * `CommerceProvider` before Sylius (ADR 0049): the LMS talks to commerce through one interface; the
 * Wellms cart is the default adapter. Providers that report orders by webhook (Phase 6.4) are received by
 * `OrderWebhookController` and applied through the entitlements package (ADR 0130).
 */
class UlamsCommerceServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'commerce';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsCartServiceProvider::class);
        $this->app->register(UlamsEntitlementsServiceProvider::class);

        $this->app->singleton(CommerceManager::class, function (Container $app) {
            $manager = new CommerceManager($app);
            $manager->extend(WellmsCartProvider::KEY, fn (Container $c) => new WellmsCartProvider($c->make(ProductServiceContract::class)));

            return $manager;
        });
        $this->app->bind(CommerceProvider::class, fn (Container $app) => $app->make(CommerceManager::class)->provider());
    }

    public function boot(): void
    {
        RateLimiter::for('commerce-webhook', fn (Request $request) => Limit::perMinute((int) config('commerce.webhook.rate_limit'))->by('commerce-webhook:' . $request->route('provider') . ':' . $request->ip()));
        Event::listen(ProductBought::class, [RecordWellmsPurchase::class, 'handleBought']);
        Event::listen(ProductAttached::class, [RecordWellmsPurchase::class, 'handleAttached']);
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // per tenant: `schedule:run` runs for the platform and for every tenant domain
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (config('commerce.reconcile.schedule')) {
                $schedule->command('ulams:commerce:reconcile')->everyFiveMinutes()->withoutOverlapping();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileCommand::class, BackfillAccessCommand::class]);
            $this->publishes([__DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php')], self::CONFIG_KEY . '.config');
        }
    }
}
