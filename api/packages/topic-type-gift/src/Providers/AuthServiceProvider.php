<?php

namespace EscolaLms\TopicTypeGift\Providers;

use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        QuizAttempt::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
