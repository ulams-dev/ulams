<?php

namespace Ulams\CoursesImportExport;

use Ulams\CoursesImportExport\Services\CloneCourseService;
use Ulams\CoursesImportExport\Services\Contracts\CloneCourseServiceContract;
use Ulams\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Ulams\CoursesImportExport\Services\ExportImportService;
use Illuminate\Support\ServiceProvider;
use Ulams\Uploads\UlamsUploadsServiceProvider;
use ZanySoft\Zip\ZipServiceProvider;

class UlamsCoursesImportExportServiceProvider extends ServiceProvider
{
    public $singletons = [
        ExportImportServiceContract::class => ExportImportService::class,
        CloneCourseServiceContract::class => CloneCourseService::class,
    ];

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'course-import-export');
    }

    public function register()
    {
        $this->app->register(UlamsUploadsServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(ZipServiceProvider::class);
    }
}
