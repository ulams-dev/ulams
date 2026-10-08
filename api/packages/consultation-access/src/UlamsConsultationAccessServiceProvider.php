<?php

namespace Ulams\ConsultationAccess;

use Ulams\ConsultationAccess\Providers\AuthServiceProvider;
use Ulams\ConsultationAccess\Repositories\ConsultationAccessEnquiryProposedTermRepository;
use Ulams\ConsultationAccess\Repositories\ConsultationAccessEnquiryRepository;
use Ulams\ConsultationAccess\Repositories\Contracts\ConsultationAccessEnquiryProposedTermRepositoryContract;
use Ulams\ConsultationAccess\Repositories\Contracts\ConsultationAccessEnquiryRepositoryContract;
use Ulams\ConsultationAccess\Services\ConsultationAccessEnquiryService;
use Ulams\ConsultationAccess\Services\Contracts\ConsultationAccessEnquiryServiceContract;
use Ulams\Consultations\UlamsConsultationsServiceProvider;
use Ulams\PencilSpaces\UlamsPencilSpacesServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsConsultationAccessServiceProvider extends ServiceProvider
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
        $this->app->register(UlamsConsultationsServiceProvider::class);
        $this->app->register(UlamsPencilSpacesServiceProvider::class);
    }
}
