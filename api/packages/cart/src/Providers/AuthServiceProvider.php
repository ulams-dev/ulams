<?php

namespace Ulams\Cart\Providers;

use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Policies\OrderPolicy;
use Ulams\Cart\Policies\ProductPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
        Product::class => ProductPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
