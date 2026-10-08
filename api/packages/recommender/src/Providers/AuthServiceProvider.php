<?php

namespace EscolaLms\Recommender\Providers;

use EscolaLms\Recommender\Models\Course;
use EscolaLms\Recommender\Models\Lesson;
use EscolaLms\Recommender\Policies\RecommenderPolicy;
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
