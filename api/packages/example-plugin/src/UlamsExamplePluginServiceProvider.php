<?php

namespace Ulams\ExamplePlugin;

use Illuminate\Support\ServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\ExamplePlugin\Connectors\ExampleConnector;
use Ulams\ExamplePlugin\Providers\SettingsServiceProvider;
use Ulams\ExamplePlugin\Services\Contracts\GreetingServiceContract;
use Ulams\ExamplePlugin\Services\GreetingService;
use Ulams\LivingCourse\Connectors\SourceConnectorRegistry;

/**
 * Example module for the "Extending ulams" guide: a public endpoint that reads an
 * administrable setting and an admin endpoint guarded by a permission that dispatches a
 * domain event. Not registered in config/app.php; see README.md to enable it.
 *
 * SWAGGER_VERSION
 */
class UlamsExamplePluginServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_example_plugin';

    public $singletons = [
        GreetingServiceContract::class => GreetingService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(UlamsAuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // a source connector for Living Course, only where Living Course is installed (docs/living-course/connector-plugins.md)
        if (class_exists(SourceConnectorRegistry::class)) {
            $this->app->afterResolving(SourceConnectorRegistry::class, fn (SourceConnectorRegistry $registry) => $registry->register(new ExampleConnector()));
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
            ], self::CONFIG_KEY . '.config');
        }
    }
}
