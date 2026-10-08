<?php

namespace Ulams\TemplatesPdf;

use Ulams\TemplatesPdf\Providers\CourseTemplatesServiceProvider;
use Ulams\TemplatesPdf\Providers\UserTemplateServiceProvider;
use Ulams\TemplatesPdf\Providers\AuthServiceProvider;
use Ulams\TemplatesPdf\Services\ReportBroService;
use Ulams\TemplatesPdf\Services\Contracts\ReportBroServiceContract;
use Illuminate\Support\ServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;

/**
 * SWAGGER_VERSION
 */
class UlamsTemplatesPdfServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_templates_pdf';

    public $singletons = [
        ReportBroServiceContract::class => ReportBroService::class
    ];

    public function register()
    {

        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        if (class_exists(\Ulams\Courses\UlamsCourseServiceProvider::class)) {
            $this->app->register(CourseTemplatesServiceProvider::class);
        }

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(UserTemplateServiceProvider::class);

        if (class_exists(\Ulams\Settings\Facades\AdministrableConfig::class)) {
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.reportbro_url', ['required', 'string'], true);
        }
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

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
