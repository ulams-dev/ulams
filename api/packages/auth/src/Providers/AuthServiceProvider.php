<?php

namespace Ulams\Auth\Providers;

use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\User;
use Ulams\Auth\Policies\ApiTokenPolicy;
use Ulams\Auth\Policies\GroupPolicy;
use Ulams\Auth\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Group::class => GroupPolicy::class,
        ApiTokenMeta::class => ApiTokenPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
    }
}
