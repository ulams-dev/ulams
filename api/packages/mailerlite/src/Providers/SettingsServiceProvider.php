<?php

namespace Ulams\MailerLite\Providers;

use Ulams\MailerLite\Enum\PackageStatusEnum;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_mailer_lite';

    public function register()
    {
        if (class_exists(UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }
        }

        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.package_status', ['required', 'string', 'in:' . implode(',', PackageStatusEnum::getValues())], false);
        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.api_key', ['required', 'string'], false);
        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.newsletter_field_key', ['required', 'string'], false);
        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.group_registered_group', ['required', 'string'], false);
        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.group_order_paid', ['required', 'string'], false);
        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.group_left_cart', ['required', 'string'], false);
    }
}
