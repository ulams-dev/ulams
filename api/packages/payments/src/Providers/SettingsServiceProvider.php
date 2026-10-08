<?php

namespace EscolaLms\Payments\Providers;

use EscolaLms\Payments\Enums\Currency;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{

    public function register()
    {
        if (class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class) && class_exists(\EscolaLms\Settings\Facades\AdministrableConfig::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }
            AdministrableConfig::registerConfig('escolalms_payments.drivers.stripe.enabled', ['required', 'boolean']);
            AdministrableConfig::registerConfig('escolalms_payments.drivers.stripe.secret_key', ['required', 'string'], false);
            AdministrableConfig::registerConfig('escolalms_payments.drivers.stripe.publishable_key', ['required', 'string'], true);

            AdministrableConfig::registerConfig('escolalms_payments.drivers.przelewy24.enabled', ['required', 'boolean']);
            AdministrableConfig::registerConfig('escolalms_payments.drivers.przelewy24.live', ['required', 'boolean'], false);
            AdministrableConfig::registerConfig('escolalms_payments.drivers.przelewy24.merchant_id', ['required', 'string'], false);
            AdministrableConfig::registerConfig('escolalms_payments.drivers.przelewy24.pos_id', ['required', 'string'], false);
            AdministrableConfig::registerConfig('escolalms_payments.drivers.przelewy24.api_key', ['required', 'string'], false);
            AdministrableConfig::registerConfig('escolalms_payments.drivers.przelewy24.crc', ['required', 'string'], false);

            AdministrableConfig::registerConfig('escolalms_payments.drivers.revenuecat.enabled', ['required', 'boolean']);

            AdministrableConfig::registerConfig('escolalms_payments.default_gateway', ['required', 'string', 'in:Free,Stripe,Przelewy24']);
            AdministrableConfig::registerConfig('escolalms_payments.default_currency', ['required', 'string', 'in:' . implode(',', Currency::getValues())]);
        }
    }
}
