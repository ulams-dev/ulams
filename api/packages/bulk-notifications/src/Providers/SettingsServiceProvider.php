<?php

namespace Ulams\BulkNotifications\Providers;

use Ulams\BulkNotifications\UlamsBulkNotificationsServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (class_exists(UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(UlamsBulkNotificationsServiceProvider::CONFIG_KEY . '.push.service_account', ['json'], false);
            AdministrableConfig::registerConfig(UlamsBulkNotificationsServiceProvider::CONFIG_KEY . '.push.base_redirect_url', ['string'], false);
        }
    }
}
