<?php

namespace EscolaLms\ConsultationAccess;

use EscolaLms\ConsultationAccess\Providers\AuthServiceProvider;
use EscolaLms\ConsultationAccess\Repositories\ConsultationAccessEnquiryProposedTermRepository;
use EscolaLms\ConsultationAccess\Repositories\ConsultationAccessEnquiryRepository;
use EscolaLms\ConsultationAccess\Repositories\Contracts\ConsultationAccessEnquiryProposedTermRepositoryContract;
use EscolaLms\ConsultationAccess\Repositories\Contracts\ConsultationAccessEnquiryRepositoryContract;
use EscolaLms\ConsultationAccess\Services\ConsultationAccessEnquiryService;
use EscolaLms\ConsultationAccess\Services\Contracts\ConsultationAccessEnquiryServiceContract;
use EscolaLms\Consultations\EscolaLmsConsultationsServiceProvider;
use EscolaLms\PencilSpaces\EscolaLmsPencilSpacesServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsConsultationAccessServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        ConsultationAccessEnquiryServiceContract::class => ConsultationAccessEnquiryService::class,
    ];

    public const REPOSITORIES = [
        ConsultationAccessEnquiryRepositoryContract::class => ConsultationAccessEnquiryRepository::class,
        ConsultationAccessEnquiryProposedTermRepositoryContract::class => ConsultationAccessEnquiryProposedTermRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(EscolaLmsConsultationsServiceProvider::class);
        $this->app->register(EscolaLmsPencilSpacesServiceProvider::class);
    }
}
