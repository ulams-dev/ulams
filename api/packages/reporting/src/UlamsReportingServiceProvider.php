<?php

namespace Ulams\Reporting;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ulams\Reporting\Datasets\AiUsageDataset;
use Ulams\Reporting\Http\Controllers\DatasetController;
use Ulams\Reporting\Http\Controllers\QueryController;
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
        $this->app->make(DatasetRegistry::class)->register(new AiUsageDataset());

        // the prefix `reporting` keeps clear of the legacy `api/admin/reports*` routes of the Wellms reports package
        Route::group(['prefix' => 'api/admin/reporting', 'middleware' => ['auth:api']], function () {
            Route::get('datasets', [DatasetController::class, 'index']);
            Route::post('query', [QueryController::class, 'run']);
        });
    }
}
