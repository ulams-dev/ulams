<?php

namespace Ulams\Translations;

use Ulams\Translations\Console\Command\MergeTranslationsOfPermissionsCommand;
use Ulams\Translations\Http\Middleware\AcceptLanguage;
use Ulams\Translations\Providers\AuthServiceProvider;
use Ulams\Translations\Repositories\Contracts\LanguageLineRepositoryContract;
use Ulams\Translations\Repositories\LanguageLineRepository;
use Ulams\Translations\Services\Contracts\LanguageLineServiceContract;
use Ulams\Translations\Services\Contracts\TranslationServiceContract;
use Ulams\Translations\Services\LanguageLineService;
use Ulams\Translations\Services\TranslationService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Spatie\TranslationLoader\TranslationServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsTranslationsServiceProvider extends ServiceProvider
{
    const REPOSITORIES = [
        LanguageLineRepositoryContract::class => LanguageLineRepository::class,
    ];

    const SERVICES = [
        LanguageLineServiceContract::class => LanguageLineService::class,
        TranslationServiceContract::class => TranslationService::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function boot()
    {
        $this->app->make(Kernel::class)->pushMiddleware(AcceptLanguage::class);
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'translation');
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'ulams_translations');
        $this->mergeConfigFrom(__DIR__ . '/../config/translation-loader.php', 'translation-loader');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(TranslationServiceProvider::class);
    }

    protected function bootForConsole(): void
    {
        $this->commands([
            MergeTranslationsOfPermissionsCommand::class,
        ]);

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('ulams_translations.php'),
            __DIR__ . '/../config/translation-loader.php' => config_path('translation-loader.php'),
        ], 'ulams_translations');
    }
}
