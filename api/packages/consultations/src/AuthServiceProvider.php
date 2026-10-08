<?php

namespace EscolaLms\Consultations;

use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Policies\ConsultationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{

    protected $policies = [
        Consultation::class => ConsultationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
