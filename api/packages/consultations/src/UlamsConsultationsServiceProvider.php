<?php

namespace Ulams\Consultations;

use Ulams\Core\Support\MorphMap;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Consultations\Console\PurgeMeetingFrames;
use Ulams\Consultations\Providers\EventServiceProvider;
use Ulams\Consultations\Repositories\ConsultationRepository;
use Ulams\Consultations\Repositories\ConsultationUserRepository;
use Ulams\Consultations\Repositories\ConsultationUserTermRepository;
use Ulams\Consultations\Repositories\Contracts\ConsultationRepositoryContract;
use Ulams\Consultations\Repositories\Contracts\ConsultationUserRepositoryContract;
use Ulams\Consultations\Repositories\Contracts\ConsultationUserTermRepositoryContract;
use Ulams\Consultations\Services\ConsultationService;
use Ulams\Consultations\Services\Contracts\ConsultationServiceContract;
use Ulams\Jitsi\UlamsJitsiServiceProvider;
use Ulams\ModelFields\ModelFieldsServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsConsultationsServiceProvider extends ServiceProvider
{
    /** Morph aliases of the models of this package (see Ulams\Core\Support\MorphMap); tests of packages that only use the models register them too. */
    public const MORPH_ALIASES = [
        'consultation' => \Ulams\Consultations\Models\Consultation::class,
        'consultation_user' => \Ulams\Consultations\Models\ConsultationUserPivot::class,
    ];

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

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    protected function bootForConsole(): void
    {
        $this->commands([PurgeMeetingFrames::class]);
        $this->publishes([
            __DIR__ . '/config.php' => config_path('config.php'),
        ], 'ulams_consultations');
    }

    public function register()
    {
        MorphMap::register(self::MORPH_ALIASES);

        $this->mergeConfigFrom(__DIR__ . '/config.php', 'ulams_consultations');
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(UlamsJitsiServiceProvider::class);
        $this->app->register(UlamsSettingsServiceProvider::class);
        $this->app->register(UlamsCategoriesServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
        $this->app->register(UlamsAuthServiceProvider::class);
        $this->app->register(ModelFieldsServiceProvider::class);
    }
}
