<?php

namespace Ulams\TemplatesSms\Providers;

use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\TemplatesSms\Enums\ConfigEnum;
use Ulams\TemplatesSms\Enums\SmsDriversEnum;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.default', ['required', 'string', 'in:' . implode(',', SmsDriversEnum::getValues())], false);

            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.drivers.requestbin.path', ['string'], false);

            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.drivers.twilio.sid', ['string'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.drivers.twilio.token', ['string'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.drivers.twilio.from', ['string'], false);
        }
    }
}
