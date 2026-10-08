<?php

namespace Ulams\Cart\Providers;

use Ulams\Cart\Listeners\PaymentSuccessListener;
use Ulams\Payments\Events\PaymentSuccess;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PaymentSuccess::class => [
            PaymentSuccessListener::class,
        ]
    ];
}
