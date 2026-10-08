<?php

namespace Ulams\Courses\Providers;

use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Listeners\SetNewDeadlineForReassignedUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Spatie\ResponseCache\Events\ClearedResponseCacheEvent;
use Spatie\ResponseCache\Facades\ResponseCache;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        CourseAssigned::class => [
            SetNewDeadlineForReassignedUser::class,
        ],
    ];

    public function boot(): void
    {
        Event::listen([
            'eloquent.created: Ulams*',
            'eloquent.updated: Ulams*',
            'eloquent.deleted: Ulams*',
        ], function() {
            ResponseCache::clear();
            event(new ClearedResponseCacheEvent());
        });
    }
}
