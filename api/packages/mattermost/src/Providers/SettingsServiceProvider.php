<?php

namespace EscolaLms\Mattermost\Providers;

use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'mattermost';

    public function register()
    {
        if (class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.package_status', ['required', 'string', 'in:' . implode(',', PackageStatusEnum::getValues())], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.host', ['required', 'string'], true);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.login', ['required', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.password', ['required', 'string'], false);
        }
    }
}
