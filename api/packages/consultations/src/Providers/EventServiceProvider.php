<?php

namespace EscolaLms\Consultations\Providers;

use EscolaLms\Consultations\Events\ReminderAboutTerm;
use EscolaLms\Consultations\Listeners\ReminderAboutTermListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ReminderAboutTerm::class => [
            ReminderAboutTermListener::class
        ]
    ];
}
