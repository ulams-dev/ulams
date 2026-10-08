<?php

namespace EscolaLms\Consultations;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Categories\EscolaLmsCategoriesServiceProvider;
use EscolaLms\Consultations\Providers\EventServiceProvider;
use EscolaLms\Consultations\Repositories\ConsultationRepository;
use EscolaLms\Consultations\Repositories\ConsultationUserRepository;
use EscolaLms\Consultations\Repositories\ConsultationUserTermRepository;
use EscolaLms\Consultations\Repositories\Contracts\ConsultationRepositoryContract;
use EscolaLms\Consultations\Repositories\Contracts\ConsultationUserRepositoryContract;
use EscolaLms\Consultations\Repositories\Contracts\ConsultationUserTermRepositoryContract;
use EscolaLms\Consultations\Services\ConsultationService;
use EscolaLms\Consultations\Services\Contracts\ConsultationServiceContract;
use EscolaLms\Jitsi\EscolaLmsJitsiServiceProvider;
use EscolaLms\ModelFields\ModelFieldsServiceProvider;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsConsultationsServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        ConsultationServiceContract::class => ConsultationService::class
    ];
    public const REPOSITORIES = [
        ConsultationRepositoryContract::class => ConsultationRepository::class,
        ConsultationUserRepositoryContract::class => ConsultationUserRepository::class,
        ConsultationUserTermRepositoryContract::class => ConsultationUserTermRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'consultation');
        $this->loadRoutesFrom(__DIR__ . '/channels.php');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('config.php'),
        ], 'escolalms_consultations');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'escolalms_consultations');
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(EscolaLmsJitsiServiceProvider::class);
        $this->app->register(EscolaLmsSettingsServiceProvider::class);
        $this->app->register(EscolaLmsCategoriesServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(EscolaLmsAuthServiceProvider::class);
        $this->app->register(ModelFieldsServiceProvider::class);
    }
}
