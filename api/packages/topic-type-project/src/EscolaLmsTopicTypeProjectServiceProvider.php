<?php

namespace EscolaLms\TopicTypeProject;

use EscolaLms\Courses\EscolaLmsCourseServiceProvider;
use EscolaLms\Courses\Facades\Topic;
use EscolaLms\TopicTypeProject\Providers\AuthServiceProvider;
use EscolaLms\TopicTypeProject\Http\Resources\TopicType\Admin\ProjectResource as AdminProjectResource;
use EscolaLms\TopicTypeProject\Http\Resources\TopicType\Client\ProjectResource as ClientProjectResource;
use EscolaLms\TopicTypeProject\Http\Resources\TopicType\Export\ProjectResource as ExportProjectResource;
use EscolaLms\TopicTypeProject\Models\Project;
use EscolaLms\TopicTypeProject\Repositories\Contracts\ProjectSolutionRepositoryContract;
use EscolaLms\TopicTypeProject\Repositories\ProjectSolutionRepository;
use EscolaLms\TopicTypeProject\Services\ProjectSolutionService;
use EscolaLms\TopicTypeProject\Services\Contracts\ProjectSolutionServiceContract;
use EscolaLms\TopicTypes\EscolaLmsTopicTypesServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsTopicTypeProjectServiceProvider extends ServiceProvider
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
        $this->app->register(EscolaLmsTopicTypesServiceProvider::class);
        $this->app->register(EscolaLmsCourseServiceProvider::class);
    }
}
