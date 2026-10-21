<?php

namespace Ulams\Entitlements;

use Illuminate\Support\ServiceProvider;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Entitlements\Services\EntitlementService;

/**
 * Entitlements (ADR 0130): what granted a user access to a course, with validity and a unique source key.
 * Core of Phase 6.4 CM-1; seat pools, the expiry job, the backfill and the `CourseAccessService` decorator follow.
 */
class UlamsEntitlementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(UlamsCourseAccessServiceProvider::class);
        $this->app->singleton(EntitlementService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
