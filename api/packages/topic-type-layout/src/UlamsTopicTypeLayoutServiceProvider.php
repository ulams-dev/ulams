<?php

namespace Ulams\TopicTypeLayout;

use Illuminate\Support\ServiceProvider;
use Ulams\Courses\Facades\Topic;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\TopicTypeLayout\Http\Resources\LayoutTopicExportResource;
use Ulams\TopicTypeLayout\Http\Resources\LayoutTopicResource;
use Ulams\TopicTypeLayout\Models\LayoutTopic;
use Ulams\TopicTypeLayout\Services\LayoutDocumentValidator;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;

/**
 * Layout topic type (ADR 0052): a validated document of approved learner components. It adds no routes:
 * topics are created and changed through the topic API.
 */
class UlamsTopicTypeLayoutServiceProvider extends ServiceProvider
{
    public $singletons = [
        LayoutDocumentValidator::class => LayoutDocumentValidator::class,
    ];

    public function register(): void
    {
        $this->app->register(UlamsTopicTypesServiceProvider::class);
        $this->app->register(UlamsCourseServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Topic::registerContentClass(LayoutTopic::class);
        Topic::registerResourceClasses(LayoutTopic::class, [
            'client' => LayoutTopicResource::class,
            'admin' => LayoutTopicResource::class,
            'export' => LayoutTopicExportResource::class,
        ]);
    }
}
