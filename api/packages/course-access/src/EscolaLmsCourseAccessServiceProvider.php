<?php

namespace EscolaLms\CourseAccess;

use EscolaLms\CourseAccess\Providers\AuthServiceProvider;
use EscolaLms\CourseAccess\Providers\SettingsServiceProvider;
use EscolaLms\CourseAccess\Repositories\Contracts\CourseAccessEnquiryRepositoryContract;
use EscolaLms\CourseAccess\Repositories\CourseAccessEnquiryRepository;
use EscolaLms\CourseAccess\Services\Contracts\CourseAccessEnquiryServiceContract;
use EscolaLms\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use EscolaLms\CourseAccess\Services\CourseAccessEnquiryService;
use EscolaLms\CourseAccess\Services\CourseAccessService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsCourseAccessServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escolalms_course_access';
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
