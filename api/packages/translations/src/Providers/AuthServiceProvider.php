<?php

namespace Ulams\Translations\Providers;

use Ulams\Translations\Models\LanguageLine;
use Ulams\Translations\Policies\LanguageLinePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        LanguageLine::class => LanguageLinePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
