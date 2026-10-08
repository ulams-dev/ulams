<?php

namespace Ulams\Courses;

use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Policies\CoursesPolicy;
use Ulams\Courses\Policies\LessonPolicy;
use Ulams\Courses\Policies\TopicPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Course::class => CoursesPolicy::class,
        Lesson::class => LessonPolicy::class,
        Topic::class => TopicPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
    }
}
