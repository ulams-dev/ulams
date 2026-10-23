<?php

namespace Ulams\Reporting;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ulams\Reporting\Datasets\AiUsageDataset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Ulams\Reporting\Http\Controllers\DatasetController;
use Ulams\Reporting\Http\Controllers\ODataController;
use Ulams\Reporting\Http\Middleware\ODataResponse;
use Ulams\Reporting\Http\Controllers\QueryController;
use Ulams\Reporting\Http\Controllers\SavedReportController;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Services\ReportingSettings;

/**
 * Report builder (ADR 0194, plan docs/plans/authoring-metric-and-report-builder.md): the registry of datasets a report
 * can be built on (RB-1) and the compiler that turns a report definition into a safe, parameterised query with
 * small-group suppression (RB-2). Packages register their own datasets (compliance, authoring metrics); this
 * package ships the AI usage dataset because `ai_calls` belongs to the platform, not to one feature.
 */
class UlamsReportingServiceProvider extends ServiceProvider
{
    public $singletons = [
        DatasetRegistry::class => DatasetRegistry::class,
        ReportingSettings::class => ReportingSettings::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'reporting');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->app->make(DatasetRegistry::class)->register(new AiUsageDataset());

        // the prefix `reporting` keeps clear of the legacy `api/admin/reports*` routes of the Wellms reports package
        Route::group(['prefix' => 'api/admin/reporting', 'middleware' => ['auth:api'], 'where' => ['id' => '[0-9A-Za-z]{26}']], function () {
            Route::get('datasets', [DatasetController::class, 'index']);
            Route::post('query', [QueryController::class, 'run']);
            Route::get('saved', [SavedReportController::class, 'index']);
            Route::post('saved', [SavedReportController::class, 'store']);
            Route::get('saved/{id}', [SavedReportController::class, 'show']);
            Route::put('saved/{id}', [SavedReportController::class, 'update']);
            Route::delete('saved/{id}', [SavedReportController::class, 'destroy']);
            Route::get('saved/{id}/run', [SavedReportController::class, 'run']);
        });

        // the read-only OData feed (RB-6): 60 requests a minute per token, Basic or Bearer credentials
        RateLimiter::for('reporting-odata', fn (Request $request) => Limit::perMinute(60)->by($request->bearerToken() ? sha1($request->bearerToken()) : $request->ip()));
        Route::group(['prefix' => 'api/reporting/odata', 'middleware' => [ODataResponse::class, 'auth:api', 'throttle:reporting-odata'], 'where' => ['entity' => '[0-9A-Za-z_]+']], function () {
            Route::get('/', [ODataController::class, 'serviceDocument']);
            Route::get('$metadata', [ODataController::class, 'metadata']);
            Route::get('{entity}', [ODataController::class, 'entitySet']);
        });
    }
}
