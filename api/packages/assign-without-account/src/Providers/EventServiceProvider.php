<?php

namespace Ulams\AssignWithoutAccount\Providers;

use Ulams\AssignWithoutAccount\Listeners\AccountRegisteredListener;
use Ulams\Auth\Events\AccountRegistered;

class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    protected $listen = [
        AccountRegistered::class => [
            AccountRegisteredListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
