<?php

namespace Ulams\ExamplePlugin\Providers;

use Illuminate\Support\ServiceProvider;
use Ulams\ExamplePlugin\UlamsExamplePluginServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\UlamsSettingsServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!class_exists(UlamsSettingsServiceProvider::class)) {
            return;
        }

        if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
            $this->app->register(UlamsSettingsServiceProvider::class);
        }

        // key, validation rules, public (returned by GET /api/config), read-only
        AdministrableConfig::registerConfig(
            UlamsExamplePluginServiceProvider::CONFIG_KEY . '.greeting',
            ['required', 'string', 'max:200'],
            true,
            false
        );
    }
}
