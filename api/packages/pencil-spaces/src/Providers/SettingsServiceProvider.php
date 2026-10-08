<?php

namespace EscolaLms\PencilSpaces\Providers;

use EscolaLms\PencilSpaces\EscolaLmsPencilSpacesServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (class_exists(EscolaLmsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }
            AdministrableConfig::registerConfig(EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY . '.api_url', ['string'], false, false);
            AdministrableConfig::registerConfig(EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY . '.api_key', ['string'], false, false);
        }
    }
}
