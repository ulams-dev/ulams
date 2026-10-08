<?php

namespace Ulams\TopicTypeGift\Providers;

use Ulams\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        QuizAttempt::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
