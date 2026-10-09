<?php

namespace Ulams\LivingCourse;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ulams\CourseBuilder\Events\SourceIngested;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\LivingCourse\Services\AuditLog;
use Ulams\LivingCourse\Services\RevisionService;

/**
 * Living Course (ADR 0030 to 0034): a course built with the Course Builder stays connected to its
 * sources. Source revisions and a deterministic fragment diff, impact analysis through citations,
 * AI update proposals the author reviews as one diff, progress-preserving apply, staleness, a
 * hash-chained audit trail, and source connectors (re-upload, Git hosts, web pages, plugins).
 */
class UlamsLivingCourseServiceProvider extends ServiceProvider
{
    public $singletons = [
        AuditLog::class => AuditLog::class,
        RevisionService::class => RevisionService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/living_course.php', 'living_course');
        $this->app->register(UlamsCourseBuilderServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // revision 1 of every source of a builder session
        Event::listen(SourceIngested::class, fn (SourceIngested $e) => $this->app->make(RevisionService::class)->ensureInitial($e->source));
    }
}
