<?php

namespace Ulams\Auth\Providers;

use Ulams\Auth\Enums\SettingStatusEnum;
use Ulams\Auth\UlamsAuthServiceProvider;
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
            AdministrableConfig::registerConfig(UlamsAuthServiceProvider::CONFIG_KEY . '.registration', ['required', 'string', 'in:' . implode(',', SettingStatusEnum::getValues())]);
            AdministrableConfig::registerConfig(UlamsAuthServiceProvider::CONFIG_KEY . '.account_must_be_enabled_by_admin', ['required', 'string', 'in:' . implode(',', SettingStatusEnum::getValues())]);
            AdministrableConfig::registerConfig(UlamsAuthServiceProvider::CONFIG_KEY . '.auto_verified_email', ['required', 'string', 'in:' . implode(',', SettingStatusEnum::getValues())]);
            AdministrableConfig::registerConfig(UlamsAuthServiceProvider::CONFIG_KEY . '.return_url', ['required', 'url']);
            AdministrableConfig::registerConfig(UlamsAuthServiceProvider::CONFIG_KEY . '.socialite_remember_me', ['required', 'boolean']);
            AdministrableConfig::registerConfig(UlamsAuthServiceProvider::CONFIG_KEY . '.token_expiration_minutes', ['required', 'integer'], false);

            // SOCIALITE
            AdministrableConfig::registerConfig('services.facebook.client_id', ['nullable', 'string'], false);
            AdministrableConfig::registerConfig('services.facebook.client_secret',  ['nullable', 'string'], false);
            AdministrableConfig::registerConfig('services.facebook.redirect', ['nullable', 'url'], false);
            AdministrableConfig::registerConfig('services.google.client_id', ['nullable', 'string'], false);
            AdministrableConfig::registerConfig('services.google.client_secret', ['nullable', 'string'], false);
            AdministrableConfig::registerConfig('services.google.redirect', ['nullable', 'url'], false);
        }
    }
}
