<?php

namespace Ulams\Courses;

use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Observers\LessonObserver;
use Ulams\Courses\Observers\TopicObserver;
use Ulams\Courses\Providers\EventServiceProvider;
use Ulams\Courses\Providers\SettingsServiceProvider;
use Ulams\Courses\Repositories\Contracts\CourseH5PProgressRepositoryContract;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\Courses\Repositories\Contracts\CourseRepositoryContract;
use Ulams\Courses\Repositories\Contracts\LessonRepositoryContract;
use Ulams\Courses\Repositories\Contracts\TopicRepositoryContract;
use Ulams\Courses\Repositories\Contracts\TopicResourceRepositoryContract;
use Ulams\Courses\Repositories\CourseH5PProgressRepository;
use Ulams\Courses\Repositories\CourseProgressRepository;
use Ulams\Courses\Repositories\CourseRepository;
use Ulams\Courses\Repositories\LessonRepository;
use Ulams\Courses\Repositories\TopicRepository;
use Ulams\Courses\Repositories\TopicResourceRepository;
use Ulams\Courses\Services\Contracts\CourseServiceContract;
use Ulams\Courses\Services\Contracts\DeadlineCalculatorServiceContract;
use Ulams\Courses\Services\Contracts\LessonServiceContract;
use Ulams\Courses\Services\Contracts\ProgressServiceContract;
use Ulams\Courses\Services\Contracts\TopicServiceContract;
use Ulams\Courses\Services\CourseService;
use Ulams\Courses\Services\DeadlineCalculatorService;
use Ulams\Courses\Services\LessonService;
use Ulams\Courses\Services\ProgressService;
use Ulams\Courses\Services\TopicService;
use Ulams\ModelFields\ModelFieldsServiceProvider;
use Ulams\Scorm\UlamsScormServiceProvider;
use Ulams\Tags\UlamsTagsServiceProvider;
use Illuminate\Support\ServiceProvider;
use Spatie\ResponseCache\Middlewares\CacheResponse;
use Spatie\ResponseCache\ResponseCacheServiceProvider;

class UlamsCourseServiceProvider extends ServiceProvider
{
    public $singletons = [
        CourseH5PProgressRepositoryContract::class => CourseH5PProgressRepository::class,
        CourseProgressRepositoryContract::class => CourseProgressRepository::class,
        CourseRepositoryContract::class => CourseRepository::class,
        CourseServiceContract::class => CourseService::class,
        ProgressServiceContract::class => ProgressService::class,
        TopicRepositoryContract::class => TopicRepository::class,
        TopicResourceRepositoryContract::class => TopicResourceRepository::class,
        LessonRepositoryContract::class => LessonRepository::class,
        TopicServiceContract::class => TopicService::class,
        LessonServiceContract::class => LessonService::class,
        DeadlineCalculatorServiceContract::class => DeadlineCalculatorService::class,
    ];

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'course');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        $router = $this->app->get('router');
        $router->aliasMiddleware('cacheResponse', CacheResponse::class);

        Topic::observe(TopicObserver::class);
        Lesson::observe(LessonObserver::class);
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('ulams_courses.php'),
        ], 'ulams_courses.config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'ulams_courses');
        $this->mergeConfigFrom(__DIR__ . '/../config/responsecache.php', 'responsecache');

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(ScheduleServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(ResponseCacheServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(UlamsScormServiceProvider::class);
        $this->app->register(UlamsTagsServiceProvider::class);
        $this->app->register(ModelFieldsServiceProvider::class);
    }
}
