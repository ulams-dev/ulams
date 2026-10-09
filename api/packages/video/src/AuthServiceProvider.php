<?php

namespace Ulams\Video;

use Ulams\Video\Models\Video;
use Ulams\Video\Policies\VideoPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Video::class => VideoPolicy::class
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
