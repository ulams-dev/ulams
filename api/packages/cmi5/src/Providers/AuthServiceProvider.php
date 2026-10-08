<?php

namespace EscolaLms\Cmi5\Providers;

use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Cmi5\Policies\Cmi5Policy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Cmi5::class => Cmi5Policy::class
    ];

    public function boot()
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
