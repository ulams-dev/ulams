<?php

namespace Ulams\Jitsi\Providers;

use Ulams\Jitsi\Enum\PackageStatusEnum;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'jitsi';

    public function register()
    {
        if (class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.package_status', ['nullable', 'string', 'in:' . implode(',', PackageStatusEnum::getValues())], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.jitsi_host', ['nullable', 'string']);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.app_id', ['nullable', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.secret', ['nullable', 'string'], false);

            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.jaas_host', ['nullable', 'string']);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.aud', ['nullable', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.iss', ['nullable', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.kid', ['nullable', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.private_key', ['nullable', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.sub', ['nullable', 'string', false]);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.recording', ['nullable', 'boolean', false]);
        }
    }
}
