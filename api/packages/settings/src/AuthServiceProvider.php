<?php

namespace EscolaLms\Settings;

use EscolaLms\Settings\Policies\SettingsPolicy;
use EscolaLms\Settings\Models\Setting;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Setting::class => SettingsPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
