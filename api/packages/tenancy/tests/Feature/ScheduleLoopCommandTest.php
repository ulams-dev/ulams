<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Ulams\Tenancy\Console\ScheduleLoopCommand;
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

    public function testOnlyOneReplicaRunsEachMinute(): void
    {
        config(['cache.default' => 'array']);
        $ran = 0;
        $this->app->make(Schedule::class)->call(function () use (&$ran) {
            $ran++;
        })->everyMinute();
        $minute = Carbon::parse('2026-10-09 10:15:20');

        $replicaA = $this->app->make(ScheduleLoopCommand::class);
        $replicaB = $this->app->make(ScheduleLoopCommand::class);
        $replicaA->setLaravel($this->app);
        $replicaB->setLaravel($this->app);

        $this->assertTrue($replicaA->runMinute($minute));
        // another replica, same minute (a few seconds later): skipped
        $this->assertFalse($replicaB->runMinute($minute->copy()->addSeconds(5)));
        $this->assertSame(1, $ran);

        // the next minute is free again, for whichever replica gets there first
        $this->assertTrue($replicaB->runMinute($minute->copy()->addMinute()));
        $this->assertSame(2, $ran);
    }

    public function testTenantsDoNotShareALock(): void
    {
        config(['cache.default' => 'array']);
        $minute = Carbon::parse('2026-10-09 10:15:00');
        $command = $this->app->make(ScheduleLoopCommand::class);
        $command->setLaravel($this->app);

        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $this->assertTrue($command->runMinute($minute));
        config(['ulams_tenancy.tenant_slug' => 'tea']);
        $this->assertTrue($command->runMinute($minute));
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $this->assertFalse($command->runMinute($minute));
    }

    public function testTheLockCanBeTurnedOff(): void
    {
        config(['cache.default' => 'array', 'ulams_tenancy.scheduler_lock' => false]);
        $minute = Carbon::parse('2026-10-09 10:15:00');
        $command = $this->app->make(ScheduleLoopCommand::class);
        $command->setLaravel($this->app);

        $this->assertTrue($command->runMinute($minute));
        $this->assertTrue($command->runMinute($minute));
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
