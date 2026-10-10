<?php

namespace Ulams\LiaScript;

use Illuminate\Support\ServiceProvider;
use Ulams\Courses\Facades\Topic;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\CoursesImportExport\Services\ExportImportService;
use Ulams\LiaScript\Http\Resources\LiaScriptTopicExportResource;
use Ulams\LiaScript\Http\Resources\LiaScriptTopicResource;
use Ulams\LiaScript\Import\LiaScriptTopicImportStrategy;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\LiaScript\Services\LiaScriptPlayer;
use Ulams\LiaScript\Services\Contracts\LiaScriptServiceContract;
use Ulams\LiaScript\Services\LiaScriptService;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Ulams\Uploads\UlamsUploadsServiceProvider;

/**
 * LiaScript (spec 1.1): versioned Markdown sources plus assets, the LiaScript topic type, and
 * playback on the tenant content origin with the LiaScript SCORM build (LiaScriptPlayer).
 */
class UlamsLiaScriptServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_liascript';

    public $singletons = [
        LiaScriptService::class => LiaScriptService::class,
        LiaScriptServiceContract::class => LiaScriptService::class,
        LiaScriptPlayer::class => LiaScriptPlayer::class,
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

        Topic::registerContentClass(LiaScriptTopic::class);
        Topic::registerResourceClasses(LiaScriptTopic::class, [
            'client' => LiaScriptTopicResource::class,
            'admin' => LiaScriptTopicResource::class,
            'export' => LiaScriptTopicExportResource::class,
        ]);
        // course export/import carries the course text (topic/<id>/liascript/ in the export)
        if (class_exists(ExportImportService::class)) {
            ExportImportService::registerTopicStrategy(LiaScriptTopic::class, LiaScriptTopicImportStrategy::class);
        }
    }
}
