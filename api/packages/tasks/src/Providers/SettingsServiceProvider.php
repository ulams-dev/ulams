<?php

namespace Ulams\Tasks\Providers;

use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Tasks\UlamsTasksServiceProvider;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{

    public function register()
    {
        if (class_exists(UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(UlamsTasksServiceProvider::CONFIG_KEY . '.notifications.overdue_period', ['integer'], false, false);
        }
    }
}
