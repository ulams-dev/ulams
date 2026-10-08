<?php

namespace Ulams\Questionnaire\Providers;

use Ulams\Questionnaire\UlamsQuestionnaireServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }
            AdministrableConfig::registerConfig(UlamsQuestionnaireServiceProvider::CONFIG_KEY . '.new_answers_visible_by_default', ['required', 'boolean']);
        }
    }
}

