<?php

namespace EscolaLms\Reports\Providers;

use EscolaLms\Reports\Metrics\Contracts\MetricContract;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use RuntimeException;

class ScheduleServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            foreach (config('reports.metrics') as $class) {
                if (class_exists($class) && is_a($class, MetricContract::class, true)) {
                    $class::make()->schedule($schedule);
                } else {
                    throw new RuntimeException("Trying to use class that doesn't exist or doesn't implement MetricContract as Report Metric");
                }
            }
        });
    }

    public function register()
    {
    }
}
