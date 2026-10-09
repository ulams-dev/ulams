<?php

namespace Ulams\Templates;

use Ulams\Templates\Models\Template;
use Ulams\Templates\Policies\TemplatePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Template::class => TemplatePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
