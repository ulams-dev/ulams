<?php

namespace EscolaLms\Translations\Providers;

use EscolaLms\Translations\Models\LanguageLine;
use EscolaLms\Translations\Policies\LanguageLinePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        LanguageLine::class => LanguageLinePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
