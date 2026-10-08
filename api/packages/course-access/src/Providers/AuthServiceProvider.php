<?php

namespace EscolaLms\CourseAccess\Providers;


use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CourseAccessEnquiry::class
    ];

    public function boot()
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
