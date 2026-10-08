<?php

namespace EscolaLms\Pages;

use EscolaLms\Pages\Models\Page;
use EscolaLms\Pages\Policies\PagePolicy;
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
