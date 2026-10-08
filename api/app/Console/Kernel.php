<?php

namespace App\Console;

use Ulams\Consultations\Enum\ConsultationTermReminderStatusEnum;
use Ulams\Consultations\Jobs\ReminderAboutConsultationJob;
use Ulams\Recommender\Jobs\RebuildTermAnalyticJob;
use Ulams\Webinar\Enum\WebinarTermReminderStatusEnum;
use Ulams\Webinar\Jobs\ReminderAboutWebinarJob;
use Illuminate\Console\Scheduling\Schedule;
use Gecche\Multidomain\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [

    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(
            new ReminderAboutWebinarJob(WebinarTermReminderStatusEnum::REMINDED_HOUR_BEFORE)
        )->everyFiveMinutes();
        $schedule->job(
            new ReminderAboutWebinarJob(WebinarTermReminderStatusEnum::REMINDED_DAY_BEFORE)
        )->everySixHours();

        $schedule->job(
            new ReminderAboutConsultationJob(ConsultationTermReminderStatusEnum::REMINDED_HOUR_BEFORE)
        )->everyMinute();
        $schedule->job(
            new ReminderAboutConsultationJob(ConsultationTermReminderStatusEnum::REMINDED_DAY_BEFORE)
        )->hourly();

        $schedule->job(new RebuildTermAnalyticJob())
            ->everyFifteenMinutes()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
