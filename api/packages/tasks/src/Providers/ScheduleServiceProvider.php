<?php

namespace Ulams\Tasks\Providers;

use Ulams\Tasks\Jobs\OverdueTaskJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider  extends ServiceProvider
{
    public function boot()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new OverdueTaskJob(0, config('ulams_tasks.notifications.overdue_period')))->daily();
        });
    }
}
