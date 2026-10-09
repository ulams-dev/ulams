<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Ulams\Tenancy\Tests\TestCase;

class ScheduleLoopCommandTest extends TestCase
{
    public function testOneTickRunsTheDueEventsInProcess(): void
    {
        $ran = 0;
        $this->app->make(Schedule::class)->call(function () use (&$ran) {
            $ran++;
        })->everyMinute();

        $this->artisan('ulams:tenant:schedule-loop', ['--once' => true])->assertExitCode(0);
        // a second tick gets a fresh start time and runs the event again
        $this->artisan('ulams:tenant:schedule-loop', ['--once' => true])->assertExitCode(0);

        $this->assertSame(2, $ran);
    }

    public function testEventsThatAreNotDueAreSkipped(): void
    {
        $ran = false;
        $this->app->make(Schedule::class)->call(function () use (&$ran) {
            $ran = true;
        })->cron('0 0 31 2 *');

        $this->artisan('ulams:tenant:schedule-loop', ['--once' => true])->assertExitCode(0);

        $this->assertFalse($ran);
    }
}
