<?php

namespace EscolaLms\Permissions;

use EscolaLms\Permissions\Models\UserAdmin;
use EscolaLms\Permissions\Policies\PermissionsPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        UserAdmin::class => PermissionsPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
        if (!Route::has('passport.authorizations.authorize') && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
