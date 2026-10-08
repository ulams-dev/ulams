<?php

namespace EscolaLms\Recommender\Providers;

use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
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

            AdministrableConfig::registerConfig(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.api_url', ['string'], false, false);
            AdministrableConfig::registerConfig(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.exercise_model', ['string'], false, false);
            AdministrableConfig::registerConfig(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.course_model', ['string'], false, false);
            AdministrableConfig::registerConfig(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.enabled', ['required', 'boolean'], true, false);
            AdministrableConfig::registerConfig(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.satisfaction_models', ['array'], false, false);
        }
    }
}
