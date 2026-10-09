<?php

namespace Ulams\TemplatesPdf;

use Illuminate\Support\ServiceProvider;
use Ulams\TemplatesPdf\Console\MigrateReportBroTemplatesCommand;
use Ulams\TemplatesPdf\Providers\AuthServiceProvider;
use Ulams\TemplatesPdf\Providers\CourseTemplatesServiceProvider;
use Ulams\TemplatesPdf\Providers\UserTemplateServiceProvider;
use Ulams\TemplatesPdf\Services\Contracts\PdfGeneratorContract;
use Ulams\TemplatesPdf\Services\Contracts\PdfRendererContract;
use Ulams\TemplatesPdf\Services\PdfGenerator;
use Ulams\TemplatesPdf\Services\PdfServiceRenderer;

/**
 * SWAGGER_VERSION
 */
class UlamsTemplatesPdfServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_templates_pdf';

    public $singletons = [
        PdfRendererContract::class => PdfServiceRenderer::class,
        PdfGeneratorContract::class => PdfGenerator::class,
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        if (class_exists(\Ulams\Courses\UlamsCourseServiceProvider::class)) {
            $this->app->register(CourseTemplatesServiceProvider::class);
        }

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(UserTemplateServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'fabricjs');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->commands([MigrateReportBroTemplatesCommand::class]);

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
