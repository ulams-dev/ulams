<?php

namespace Ulams\Tags;

use Ulams\Tags\Models\Tag;
use Ulams\Tags\Policies\TagPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Tag::class => TagPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
