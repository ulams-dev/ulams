<?php

namespace Ulams\Invoices\Providers;

use Ulams\Courses\Enum\CourseVisibilityEnum;
use Ulams\Courses\Enum\PlatformVisibility;
use Ulams\Invoices\UlamsInvoicesServiceProvider;
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

            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.date.pay_until_days', ['required', 'integer']);

            // currency
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.currency.code', ['required', 'string']);
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.currency.fraction', ['required', 'string']);
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.currency.symbol', ['required', 'string']);

            // seller
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.name', ['required', 'string']);
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.address', ['required', 'string']);
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.code', ['required', 'string']);
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.vat', ['required', 'string']);
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.phone', ['nullable', 'string']);
            AdministrableConfig::registerConfig(UlamsInvoicesServiceProvider::CONFIG_KEY . '.seller.attributes.SWIFT', ['nullable', 'string']);
        }
    }
}
