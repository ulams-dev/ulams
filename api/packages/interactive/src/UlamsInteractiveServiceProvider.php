<?php

namespace Ulams\Interactive;

use Illuminate\Support\ServiceProvider;
use Ulams\Courses\Facades\Topic;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\CoursesImportExport\Services\ExportImportService;
use Ulams\Interactive\Http\Resources\InteractiveTopicExportResource;
use Ulams\Interactive\Http\Resources\InteractiveTopicResource;
use Ulams\Interactive\Models\InteractiveTopic;
use Ulams\Interactive\Services\Contracts\InteractivePackageServiceContract;
use Ulams\Interactive\Services\InteractiveCsp;
use Ulams\Interactive\Services\InteractivePackageService;
use Ulams\Interactive\Services\InteractiveProgressService;
use Ulams\Interactive\Services\ManifestValidator;
use Ulams\Settings\Services\Contracts\AdministrableConfigServiceContract;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Ulams\Uploads\UlamsUploadsServiceProvider;

/**
 * Interactive topic type (ADR 0086): versioned zip packages with a manifest, played in an opaque
 * sandbox from the tenant content origin and talking to the lesson page through the ulams-ix bridge
 * (ADR 0087).
 */
class UlamsInteractiveServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_interactive';

    public $singletons = [
        InteractivePackageServiceContract::class => InteractivePackageService::class,
        InteractiveProgressService::class => InteractiveProgressService::class,
        ManifestValidator::class => ManifestValidator::class,
        InteractiveCsp::class => InteractiveCsp::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsUploadsServiceProvider::class);
        $this->app->register(UlamsTopicTypesServiceProvider::class);
        $this->app->register(UlamsCourseServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Topic::registerContentClass(InteractiveTopic::class);
        Topic::registerResourceClasses(InteractiveTopic::class, [
            'client' => InteractiveTopicResource::class,
            'admin' => InteractiveTopicResource::class,
            'export' => InteractiveTopicExportResource::class,
        ]);
        // course export/import carries the package (topic/<id>/interactive/ in the export)
        if (class_exists(ExportImportService::class)) {
            ExportImportService::registerTopicStrategy(InteractiveTopic::class, Import\InteractiveTopicImportStrategy::class);
        }

        // `GET /api/config` → ulams_interactive: {enabled, allow_network}; the admin reads them
        if ($this->app->bound(AdministrableConfigServiceContract::class)) {
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.enabled', ['required', 'boolean'], true);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.allow_network', ['required', 'boolean'], true);
        }
    }
}
