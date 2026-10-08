<?php

namespace EscolaLms\AssignWithoutAccount;

use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use EscolaLms\AssignWithoutAccount\Policies\UserSubmissionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        UserSubmission::class => UserSubmissionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
