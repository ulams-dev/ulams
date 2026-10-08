<?php

namespace Ulams\LiaScript;

use Illuminate\Support\ServiceProvider;
use Ulams\LiaScript\Services\LiaScriptService;
use Ulams\Uploads\UlamsUploadsServiceProvider;

/**
 * LiaScript course sources: versioned Markdown plus assets (spec 1.1). Rendering (packaging the
 * vendored LiaScript SCORM build) is a follow-up, see docs/plans/phase-1.md section 5.
 */
class UlamsLiaScriptServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_liascript';

    public $singletons = [
        LiaScriptService::class => LiaScriptService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsUploadsServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
