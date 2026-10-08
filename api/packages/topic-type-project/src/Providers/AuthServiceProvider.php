<?php

namespace EscolaLms\TopicTypeProject\Providers;

use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        ProjectSolution::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
