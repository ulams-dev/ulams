<?php

namespace EscolaLms\BulkNotifications\Providers;

use EscolaLms\BulkNotifications\EscolaLmsBulkNotificationsServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (class_exists(EscolaLmsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(EscolaLmsBulkNotificationsServiceProvider::CONFIG_KEY . '.push.service_account', ['json'], false);
            AdministrableConfig::registerConfig(EscolaLmsBulkNotificationsServiceProvider::CONFIG_KEY . '.push.base_redirect_url', ['string'], false);
        }
    }
}
