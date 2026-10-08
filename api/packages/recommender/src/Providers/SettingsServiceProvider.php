<?php

namespace Ulams\Recommender\Providers;

use Ulams\Recommender\UlamsRecommenderServiceProvider;
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

            AdministrableConfig::registerConfig(UlamsRecommenderServiceProvider::CONFIG_KEY . '.api_url', ['string'], false, false);
            AdministrableConfig::registerConfig(UlamsRecommenderServiceProvider::CONFIG_KEY . '.exercise_model', ['string'], false, false);
            AdministrableConfig::registerConfig(UlamsRecommenderServiceProvider::CONFIG_KEY . '.course_model', ['string'], false, false);
            AdministrableConfig::registerConfig(UlamsRecommenderServiceProvider::CONFIG_KEY . '.enabled', ['required', 'boolean'], true, false);
            AdministrableConfig::registerConfig(UlamsRecommenderServiceProvider::CONFIG_KEY . '.satisfaction_models', ['array'], false, false);
        }
    }
}
