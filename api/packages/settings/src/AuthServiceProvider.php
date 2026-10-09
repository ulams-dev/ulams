<?php

namespace Ulams\Settings;

use Ulams\Settings\Policies\SettingsPolicy;
use Ulams\Settings\Models\Setting;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Setting::class => SettingsPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
