<?php

namespace Ulams\Consultations;

use Ulams\Consultations\Models\Consultation;
use Ulams\Consultations\Policies\ConsultationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Consultation::class => ConsultationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
