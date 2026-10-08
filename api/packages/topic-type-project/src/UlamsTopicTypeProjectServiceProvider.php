<?php

namespace Ulams\TopicTypeProject;

use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Courses\Facades\Topic;
use Ulams\TopicTypeProject\Providers\AuthServiceProvider;
use Ulams\TopicTypeProject\Http\Resources\TopicType\Admin\ProjectResource as AdminProjectResource;
use Ulams\TopicTypeProject\Http\Resources\TopicType\Client\ProjectResource as ClientProjectResource;
use Ulams\TopicTypeProject\Http\Resources\TopicType\Export\ProjectResource as ExportProjectResource;
use Ulams\TopicTypeProject\Models\Project;
use Ulams\TopicTypeProject\Repositories\Contracts\ProjectSolutionRepositoryContract;
use Ulams\TopicTypeProject\Repositories\ProjectSolutionRepository;
use Ulams\TopicTypeProject\Services\ProjectSolutionService;
use Ulams\TopicTypeProject\Services\Contracts\ProjectSolutionServiceContract;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsTopicTypeProjectServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        ProjectSolutionServiceContract::class => ProjectSolutionService::class,
    ];

    public const REPOSITORIES = [
        ProjectSolutionRepositoryContract::class => ProjectSolutionRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Topic::registerContentClass(Project::class);
        Topic::registerResourceClasses(Project::class, [
            'client' => ClientProjectResource::class,
            'admin' => AdminProjectResource::class,
            'export' => ExportProjectResource::class,
        ]);
    }

    public function register(): void
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(UlamsTopicTypesServiceProvider::class);
        $this->app->register(UlamsCourseServiceProvider::class);
    }
}
