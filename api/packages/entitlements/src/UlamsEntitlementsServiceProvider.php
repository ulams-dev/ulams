<?php

namespace Ulams\Entitlements;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\CourseAccess\Services\CourseAccessService;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Entitlements\Console\ExpireCommand;
use Ulams\Entitlements\Console\ParityCommand;
use Ulams\Entitlements\Models\ParityReport;
use Ulams\Entitlements\Services\EntitlementCourseAccess;
use Ulams\Entitlements\Services\EntitlementService;
use Ulams\Entitlements\Support\AccessParity;

/**
 * Entitlements (ADR 0130): what granted a user access to a course, with validity and a unique source key.
 * `EntitlementService` writes `course_user` through the plain `CourseAccessService`; everyone else gets the
 * decorated one, which also records an entitlement for every access it writes. Seat pools follow in CM-9.
 */
class UlamsEntitlementsServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'entitlements';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsCourseAccessServiceProvider::class);

        // the projection writer must not go through the decorator it feeds (a contextual binding would not help:
        // `extend()` decorates whatever the container resolves for the contract, context or not)
        $this->app->singleton(EntitlementService::class, fn () => new EntitlementService(new CourseAccessService()));
        $this->app->singleton(AccessParity::class);
        $this->app->extend(
            CourseAccessServiceContract::class,
            fn (CourseAccessServiceContract $inner, Container $app) => $inner instanceof EntitlementCourseAccess
                ? $inner
                : new EntitlementCourseAccess($inner, $app->make(EntitlementService::class)),
        );
    }

    private function backfilled(): bool
    {
        try {
            return ParityReport::query()->where('kind', ParityReport::KIND_BACKFILL)->where('status', ParityReport::STATUS_PASSED)->exists();
        } catch (\Throwable) {
            return false; // not migrated yet
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // per tenant: `schedule:run` runs for the platform and for every tenant domain
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (config('entitlements.expire.schedule')) {
                $schedule->command('ulams:entitlements:expire')->hourly()->withoutOverlapping();
            }
            if (config('entitlements.parity.schedule')) {
                // only once the tenant's access has been backfilled: before that every row is a difference by design
                $schedule->command('ulams:entitlements:parity')->dailyAt('03:30')->withoutOverlapping()->when(fn () => $this->backfilled());
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireCommand::class, ParityCommand::class]);
            $this->publishes([__DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php')], self::CONFIG_KEY . '.config');
        }
    }
}
