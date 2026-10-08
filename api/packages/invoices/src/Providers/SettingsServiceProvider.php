<?php

namespace EscolaLms\Invoices\Providers;

use EscolaLms\Courses\Enum\CourseVisibilityEnum;
use EscolaLms\Courses\Enum\PlatformVisibility;
use EscolaLms\Invoices\EscolaLmsInvoicesServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.date.pay_until_days', ['required', 'integer']);

            // currency
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.currency.code', ['required', 'string']);
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.currency.fraction', ['required', 'string']);
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.currency.symbol', ['required', 'string']);

            // seller
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.name', ['required', 'string']);
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.address', ['required', 'string']);
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.code', ['required', 'string']);
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.vat', ['required', 'string']);
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.phone', ['nullable', 'string']);
            AdministrableConfig::registerConfig(EscolaLmsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.SWIFT', ['nullable', 'string']);
        }
    }
}
