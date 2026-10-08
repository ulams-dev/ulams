<?php

namespace EscolaLms\Tasks\Providers;

use EscolaLms\Tasks\Policies\TaskNotePolicy;
use EscolaLms\Tasks\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        TaskPolicy::class,
        TaskNotePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
