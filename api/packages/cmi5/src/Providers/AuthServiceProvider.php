<?php

namespace Ulams\Cmi5\Providers;

use Ulams\Cmi5\Models\Cmi5;
use Ulams\Cmi5\Policies\Cmi5Policy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Cmi5::class => Cmi5Policy::class
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
