<?php

namespace Ulams\Bookmarks\Providers;

use Ulams\Bookmarks\Policies\BookmarkPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        BookmarkPolicy::class
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
