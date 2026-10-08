<?php

namespace EscolaLms\Tasks\Providers;

use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use EscolaLms\Tasks\EscolaLmsTasksServiceProvider;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{

    public function register()
    {
        if (class_exists(EscolaLmsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(EscolaLmsTasksServiceProvider::CONFIG_KEY . '.notifications.overdue_period', ['integer'], false, false);
        }
    }
}
