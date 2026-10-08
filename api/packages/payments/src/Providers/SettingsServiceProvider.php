<?php

namespace Ulams\Payments\Providers;

use Ulams\Payments\Enums\Currency;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{

    public function register()
    {
        if (class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class) && class_exists(\Ulams\Settings\Facades\AdministrableConfig::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }
            AdministrableConfig::registerConfig('ulams_payments.drivers.stripe.enabled', ['required', 'boolean']);
            AdministrableConfig::registerConfig('ulams_payments.drivers.stripe.secret_key', ['required', 'string'], false);
            AdministrableConfig::registerConfig('ulams_payments.drivers.stripe.publishable_key', ['required', 'string'], true);

            AdministrableConfig::registerConfig('ulams_payments.drivers.przelewy24.enabled', ['required', 'boolean']);
            AdministrableConfig::registerConfig('ulams_payments.drivers.przelewy24.live', ['required', 'boolean'], false);
            AdministrableConfig::registerConfig('ulams_payments.drivers.przelewy24.merchant_id', ['required', 'string'], false);
            AdministrableConfig::registerConfig('ulams_payments.drivers.przelewy24.pos_id', ['required', 'string'], false);
            AdministrableConfig::registerConfig('ulams_payments.drivers.przelewy24.api_key', ['required', 'string'], false);
            AdministrableConfig::registerConfig('ulams_payments.drivers.przelewy24.crc', ['required', 'string'], false);

            AdministrableConfig::registerConfig('ulams_payments.drivers.revenuecat.enabled', ['required', 'boolean']);

            AdministrableConfig::registerConfig('ulams_payments.default_gateway', ['required', 'string', 'in:Free,Stripe,Przelewy24']);
            AdministrableConfig::registerConfig('ulams_payments.default_currency', ['required', 'string', 'in:' . implode(',', Currency::getValues())]);
        }
    }
}
