<?php

namespace EscolaLms\Tasks\Providers;

use EscolaLms\Tasks\Jobs\OverdueTaskJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider  extends ServiceProvider
{
    public function boot()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new OverdueTaskJob(0, config('escolalms_tasks.notifications.overdue_period')))->daily();
        });
    }
}
