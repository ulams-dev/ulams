<?php

namespace Ulams\ModelFields;

use Ulams\ModelFields\Models\Metadata;
use Ulams\ModelFields\Policies\MetadataPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Metadata::class => MetadataPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
