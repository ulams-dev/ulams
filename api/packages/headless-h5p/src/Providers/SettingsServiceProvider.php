<?php

namespace Ulams\HeadlessH5P\Providers;

use Ulams\HeadlessH5P\Enums\ConfigEnum;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (class_exists(UlamsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsSettingsServiceProvider::class)) {
                $this->app->register(UlamsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_show_display_option', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_frame', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_export', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_embed', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_copyright', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_icon', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_track_user', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_ext_communication', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_save_content_state', ['boolean'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.h5p_save_content_frequency', ['numeric'], false);
            AdministrableConfig::registerConfig(ConfigEnum::CONFIG_KEY . '.url', ['string'], false);
        }
    }
}
