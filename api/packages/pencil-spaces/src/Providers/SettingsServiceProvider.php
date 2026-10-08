<?php

namespace Ulams\PencilSpaces\Providers;

use Ulams\PencilSpaces\UlamsPencilSpacesServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (class_exists(UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }
            AdministrableConfig::registerConfig(UlamsPencilSpacesServiceProvider::CONFIG_KEY . '.api_url', ['string'], false, false);
            AdministrableConfig::registerConfig(UlamsPencilSpacesServiceProvider::CONFIG_KEY . '.api_key', ['string'], false, false);
        }
    }
}
