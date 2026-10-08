<?php

namespace Ulams\Images\Providers;

use Ulams\Images\Enum\PackageStatusEnum;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'images';

    public function register()
    {
        if (class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.private.rate_limiter_status', ['required', 'string', 'in:' . implode(',', PackageStatusEnum::getValues())], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.private.rate_limit_global', ['required', 'numeric'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.private.rate_limit_per_ip', ['required', 'numeric'], false);
        }
    }
}
