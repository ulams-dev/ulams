<?php

namespace Ulams\Settings;

use Ulams\Settings\AuthServiceProvider;
use Ulams\Settings\ConfigRewriter\ConfigRepositoryExtension;
use Ulams\Settings\ConfigRewriter\ConfigRewriter;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\Repositories\Contracts\SettingsRepositoryContract;
use Ulams\Settings\Repositories\SettingsRepository;
use Ulams\Settings\Services\AdministrableConfigService;
use Ulams\Settings\Services\Contracts\AdministrableConfigServiceContract;
use Ulams\Settings\Services\Contracts\SettingsServiceContract;
use Ulams\Settings\Services\SettingsService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsSettingsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        SettingsRepositoryContract::class => SettingsRepository::class,
        SettingsServiceContract::class => SettingsService::class,
        AdministrableConfigServiceContract::class => AdministrableConfigService::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [];

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'settings');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        if (!AdministrableConfig::loadConfigFromCache()) {
            AdministrableConfig::loadConfigFromDatabase();
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'ulams_settings');

        $this->app->register(AuthServiceProvider::class);

        $this->app->singleton(ConfigRepositoryExtension::class, function ($app, $items) {
            $writer = new ConfigRewriter(resolve('files'), App::configPath());
            return new ConfigRepositoryExtension($writer, $items);
        });

        $this->app->extend('config', function ($config, $app) {
            $config_items = $config->all();
            return $app->make(ConfigRepositoryExtension::class, $config_items);
        });
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('ulams_settings.php'),
        ], 'ulams_settings.config');
    }
}
