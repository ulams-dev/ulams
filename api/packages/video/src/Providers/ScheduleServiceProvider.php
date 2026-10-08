<?php

namespace EscolaLms\Video\Providers;

use EscolaLms\Video\Jobs\DetectStuckVideo;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(DetectStuckVideo::class)->everyThirtyMinutes();
        });
    }
}

