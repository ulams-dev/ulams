<?php

namespace Ulams\KnowledgeExport;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Courses\Events\CourseStatusChanged;
use Ulams\KnowledgeExport\Services\ChangeListener;
use Ulams\TopicTypes\Events\TopicTypeChanged;

/**
 * Knowledge export (Phase 7.6): a course, or every published course of the tenant, as a pack of chunks with
 * citations and metadata for RAG in company agents, re-exported when the course changes. SWAGGER_VERSION
 */
class UlamsKnowledgeExportServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'knowledge_export';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsAuthServiceProvider::class);
    }

    public function boot(): void
    {
        RateLimiter::for('ulams-knowledge', fn (Request $request) => Limit::perMinute((int) config('knowledge_export.api_rate_limit'))->by('knowledge:' . ($request->user()?->getKey() ?? $request->ip())));
        RateLimiter::for('ulams-knowledge-start', fn (Request $request) => Limit::perMinute((int) config('knowledge_export.start_rate_limit'))->by('knowledge-start:' . ($request->user()?->getKey() ?? $request->ip())));

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Event::listen(TopicTypeChanged::class, [ChangeListener::class, 'topicChanged']);
        Event::listen(CourseStatusChanged::class, [ChangeListener::class, 'courseChanged']);

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php')], self::CONFIG_KEY . '.config');
        }
    }
}
