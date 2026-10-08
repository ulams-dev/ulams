<?php

namespace Ulams\TopicTypeProject\Providers;

use Ulams\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        ProjectSolution::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
