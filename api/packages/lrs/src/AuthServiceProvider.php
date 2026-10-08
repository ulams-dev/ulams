<?php

namespace Ulams\Lrs;

use Ulams\Lrs\Extensions\AccessTokenGuard;
use Ulams\Lrs\Models\Statement;
use Ulams\Lrs\Policies\StatementPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Statement::class => StatementPolicy::class
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Auth::extend('access_token', function () {
            $request = app('request');
            return new AccessTokenGuard($request);
        });
    }
}
