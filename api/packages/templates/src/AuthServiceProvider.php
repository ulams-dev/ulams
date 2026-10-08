<?php

namespace EscolaLms\Templates;

use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Policies\TemplatePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Template::class => TemplatePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
