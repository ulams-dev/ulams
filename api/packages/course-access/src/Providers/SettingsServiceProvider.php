<?php

namespace Ulams\CourseAccess\Providers;

use Ulams\Auth\Enums\SettingStatusEnum;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
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
            AdministrableConfig::registerConfig(UlamsCourseAccessServiceProvider::CONFIG_KEY . '.auto_accept_access_request', ['required', 'string', 'in:' . implode(',', SettingStatusEnum::getValues())]);
        }
    }
}
