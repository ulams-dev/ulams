<?php

namespace Ulams\Adapt;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ulams\Adapt\Http\Controllers\AdaptSourceController;
use Ulams\Adapt\Services\AdaptSourceValidator;
use Ulams\Scorm\UlamsScormServiceProvider;

/**
 * Adapt Learning, Path B: versioned Adapt JSON sources built into SCORM by an isolated GPL-3.0
 * worker (ADR 0013), behind ADAPT_SOURCE_ENABLED. Path A (Adapt SCORM exports) lives in the SCORM
 * package (source_format = adapt).
 */
class UlamsAdaptServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_adapt';

    public $singletons = [
        AdaptSourceValidator::class => AdaptSourceValidator::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsScormServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Route::group(['prefix' => 'api/admin/adapt', 'middleware' => ['auth:api'], 'where' => ['id' => '[0-9]+']], function () {
            Route::get('/', [AdaptSourceController::class, 'index']);
            Route::post('/', [AdaptSourceController::class, 'store']);
            Route::get('{id}', [AdaptSourceController::class, 'show']);
            Route::delete('{id}', [AdaptSourceController::class, 'destroy']);
            Route::get('{id}/source', [AdaptSourceController::class, 'source']);
            Route::post('{id}/versions', [AdaptSourceController::class, 'addVersion']);
            Route::post('{id}/build', [AdaptSourceController::class, 'build']);
        });
    }
}
