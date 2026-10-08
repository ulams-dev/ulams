<?php

namespace Ulams\Mattermost\Providers;

use Ulams\Mattermost\Enum\PackageStatusEnum;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'mattermost';

    public function register()
    {
        if (class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.package_status', ['required', 'string', 'in:' . implode(',', PackageStatusEnum::getValues())], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.host', ['required', 'string'], true);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.login', ['required', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.password', ['required', 'string'], false);
        }
    }
}
