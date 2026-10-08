<?php

namespace EscolaLms\Categories;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Categories\Policies\CategoryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Category::class => CategoryPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
