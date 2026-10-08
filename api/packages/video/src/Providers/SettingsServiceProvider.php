<?php

namespace EscolaLms\Video\Providers;

use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escolalms_video';

    public function register()
    {
        if (class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.bitrates', ['array'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.enable', ['boolean'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.non_strict_value', ['boolean'], false);
        }
    }
}
