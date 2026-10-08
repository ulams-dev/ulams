<?php

namespace Ulams\Recommender\Providers;

use Ulams\Recommender\Models\Course;
use Ulams\Recommender\Models\Lesson;
use Ulams\Recommender\Policies\RecommenderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Course::class => RecommenderPolicy::class,
        Lesson::class => RecommenderPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
