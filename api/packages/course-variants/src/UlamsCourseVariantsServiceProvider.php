<?php

namespace Ulams\CourseVariants;

use Illuminate\Support\ServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\CourseVariants\Services\CourseVariantService;

/**
 * Course variants, LMS side (ADR 0192): families of courses that are variants of one course by level and role. The
 * Course Builder registers the courses it applies; this package does not depend on the builder.
 */
class UlamsCourseVariantsServiceProvider extends ServiceProvider
{
    public $singletons = [
        CourseVariantServiceContract::class => CourseVariantService::class,
    ];

    public function register(): void
    {
        $this->app->register(UlamsCourseServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
