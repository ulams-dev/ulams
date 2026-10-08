<?php

namespace Ulams\Webinar;

use Ulams\Webinar\Models\Webinar;
use Ulams\Webinar\Policies\WebinarPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Webinar::class => WebinarPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
