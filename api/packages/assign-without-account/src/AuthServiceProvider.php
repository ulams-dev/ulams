<?php

namespace Ulams\AssignWithoutAccount;

use Ulams\AssignWithoutAccount\Models\UserSubmission;
use Ulams\AssignWithoutAccount\Policies\UserSubmissionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

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
    }
}
