<?php

namespace EscolaLms\CourseAccess\Providers;

use EscolaLms\Auth\Enums\SettingStatusEnum;
use EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (class_exists(EscolaLmsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }
            AdministrableConfig::registerConfig(EscolaLmsCourseAccessServiceProvider::CONFIG_KEY . '.auto_accept_access_request', ['required', 'string', 'in:' . implode(',', SettingStatusEnum::getValues())]);
        }
    }
}
