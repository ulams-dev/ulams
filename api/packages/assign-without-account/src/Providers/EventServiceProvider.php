<?php

namespace EscolaLms\AssignWithoutAccount\Providers;

use EscolaLms\AssignWithoutAccount\Listeners\AccountRegisteredListener;
use EscolaLms\Auth\Events\AccountRegistered;

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
