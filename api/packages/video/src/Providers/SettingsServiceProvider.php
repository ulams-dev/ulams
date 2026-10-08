<?php

namespace Ulams\Video\Providers;

use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_video';

    public function register()
    {
        if (class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.bitrates', ['array'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.enable', ['boolean'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.non_strict_value', ['boolean'], false);
        }
    }
}
