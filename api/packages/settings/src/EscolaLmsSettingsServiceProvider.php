<?php

namespace EscolaLms\Settings;

use EscolaLms\Settings\AuthServiceProvider;
use EscolaLms\Settings\ConfigRewriter\ConfigRepositoryExtension;
use EscolaLms\Settings\ConfigRewriter\ConfigRewriter;
use EscolaLms\Settings\Facades\AdministrableConfig;
use EscolaLms\Settings\Repositories\Contracts\SettingsRepositoryContract;
use EscolaLms\Settings\Repositories\SettingsRepository;
use EscolaLms\Settings\Services\AdministrableConfigService;
use EscolaLms\Settings\Services\Contracts\AdministrableConfigServiceContract;
use EscolaLms\Settings\Services\Contracts\SettingsServiceContract;
use EscolaLms\Settings\Services\SettingsService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsSettingsServiceProvider extends ServiceProvider
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
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'escola_settings');

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
            __DIR__ . '/config.php' => config_path('escola_settings.php'),
        ], 'escola_settings.config');
    }
}
