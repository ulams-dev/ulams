<?php

namespace Ulams\CourseAccess;

use Ulams\CourseAccess\Providers\AuthServiceProvider;
use Ulams\CourseAccess\Providers\SettingsServiceProvider;
use Ulams\CourseAccess\Repositories\Contracts\CourseAccessEnquiryRepositoryContract;
use Ulams\CourseAccess\Repositories\CourseAccessEnquiryRepository;
use Ulams\CourseAccess\Services\Contracts\CourseAccessEnquiryServiceContract;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\CourseAccess\Services\CourseAccessEnquiryService;
use Ulams\CourseAccess\Services\CourseAccessService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsCourseAccessServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_course_access';
    public const SERVICES = [
        CourseAccessServiceContract::class => CourseAccessService::class,
        CourseAccessEnquiryServiceContract::class => CourseAccessEnquiryService::class,
    ];

    public const REPOSITORIES = [
        CourseAccessEnquiryRepositoryContract::class => CourseAccessEnquiryRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
    }
}
