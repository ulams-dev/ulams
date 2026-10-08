<?php


namespace EscolaLms\Tags;

use EscolaLms\Tags\Models\Tag;
use EscolaLms\Tags\Policies\TagPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Tag::class => TagPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        if (! $this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
