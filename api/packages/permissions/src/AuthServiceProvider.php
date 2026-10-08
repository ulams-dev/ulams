<?php

namespace Ulams\Permissions;

use Ulams\Permissions\Models\UserAdmin;
use Ulams\Permissions\Policies\PermissionsPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        UserAdmin::class => PermissionsPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
