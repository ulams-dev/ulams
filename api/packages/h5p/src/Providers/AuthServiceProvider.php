<?php

namespace Ulams\H5P\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Ulams\H5P\Models\H5PContent;
use Ulams\H5P\Policies\H5PContentPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        H5PContent::class => H5PContentPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
