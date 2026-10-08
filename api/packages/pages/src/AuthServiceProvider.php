<?php

namespace Ulams\Pages;

use Ulams\Pages\Models\Page;
use Ulams\Pages\Policies\PagePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Page::class => PagePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
