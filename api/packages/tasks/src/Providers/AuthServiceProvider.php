<?php

namespace Ulams\Tasks\Providers;

use Ulams\Tasks\Policies\TaskNotePolicy;
use Ulams\Tasks\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        TaskPolicy::class,
        TaskNotePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
