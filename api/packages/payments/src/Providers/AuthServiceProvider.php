<?php

namespace EscolaLms\Payments\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;
use EscolaLms\Payments\Models\Payment;
use EscolaLms\Payments\Policies\PaymentPolicy;
use Illuminate\Support\Facades\Route;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Payment::class => PaymentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        if (!Route::has('passport.authorizations.authorize') && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
