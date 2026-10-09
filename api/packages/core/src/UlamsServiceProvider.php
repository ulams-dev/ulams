<?php

namespace Ulams\Core;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Database\Events\SchemaLoaded;
use Illuminate\Support\Facades\Event;
use Ulams\Core\Support\SchemaColumns;
use Ulams\Core\Http\Middleware\EnforceTrustedOrigin;
use Ulams\Core\Http\Middleware\Idempotency;
use Ulams\Core\Http\Middleware\ProtectJsonResponses;
use Ulams\Core\Http\Middleware\RequestId;
use Ulams\Core\Http\Middleware\SetTimezoneForUserMiddleware;
use Ulams\Core\Services\Contracts\HealthCheckServiceContract;
use Ulams\Core\Services\HealthCheckService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class UlamsServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        HealthCheckServiceContract::class => HealthCheckService::class,
    ];

    public array $bindings = self::SERVICES;

    public function register()
    {
        // @phpstan-ignore-next-line
        if (!$this->app->getProviders(\Ulams\ModelFields\ModelFieldsServiceProvider::class)
            && class_exists(\Ulams\ModelFields\ModelFieldsServiceProvider::class)) {
            $this->app->register(\Ulams\ModelFields\ModelFieldsServiceProvider::class);
        }
    }

    public function boot()
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(SetTimezoneForUserMiddleware::class);
        $kernel->prependMiddleware(EnforceTrustedOrigin::class);
        $kernel->pushMiddleware(ProtectJsonResponses::class);
        $kernel->prependMiddleware(RequestId::class);
        // package routes are not in the `api` group, so the route-level middleware is attached on match
        Event::listen(RouteMatched::class, fn (RouteMatched $e) => $e->route->middleware(Idempotency::class));

        $this->loadConfig();
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrations();

        // memoised column listings must not outlive a schema change in this process
        Event::listen([MigrationsEnded::class, SchemaLoaded::class], fn () => SchemaColumns::flush());
    }

    private function loadConfig(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('ulams/core.php'),
        ], 'ulams');

        $this->mergeConfigFrom(
            __DIR__ . '/config.php',
            'ulams.core'
        );

        $config = $this->app->make('config');
        $config->set('auth.guards', array_merge(
            [
                'api' => [
                    'driver' => 'passport',
                    'provider' => 'users',
                ],
            ],
            $config->get('auth.guards', [])
        ));
    }

    private function loadMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ulams');

        if (!config('ulams.core.ignore_migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
