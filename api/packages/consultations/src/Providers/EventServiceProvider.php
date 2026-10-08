<?php

namespace Ulams\Consultations\Providers;

use Ulams\Consultations\Events\ReminderAboutTerm;
use Ulams\Consultations\Listeners\ReminderAboutTermListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ReminderAboutTerm::class => [
            ReminderAboutTermListener::class
        ]
    ];
}
