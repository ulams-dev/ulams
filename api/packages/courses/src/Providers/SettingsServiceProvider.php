<?php

namespace Ulams\Courses\Providers;

use Ulams\Courses\Enum\CourseVisibilityEnum;
use Ulams\Courses\Enum\PlatformVisibility;
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

            AdministrableConfig::registerConfig('ulams_courses.platform_visibility', ['required', 'string', 'in:' . implode(',', PlatformVisibility::getValues())]);
            AdministrableConfig::registerConfig('ulams_courses.reminder_of_deadline_count_days', ['integer', 'min: 1']);
            AdministrableConfig::registerConfig('ulams_courses.course_visibility', ['required', 'string', 'in:' . implode(',', CourseVisibilityEnum::getValues())]);
        }
    }
}
