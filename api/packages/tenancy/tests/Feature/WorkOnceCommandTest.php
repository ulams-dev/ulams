<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Tests\TestCase;

class WorkOnceCommandTest extends TestCase
{
    public static int $ran = 0;

    protected function setUp(): void
    {
        parent::setUp();

        self::$ran = 0;
        config(['queue.connections.work_once' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]]);
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function ($table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function ($table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function testDrainsTheQueueAndExits(): void
    {
        dispatch(function () {
            WorkOnceCommandTest::$ran++;
        })->onConnection('work_once')->onQueue('default');
        dispatch(function () {
            WorkOnceCommandTest::$ran++;
        })->onConnection('work_once')->onQueue('default');

        $this->artisan('ulams:tenant:work-once', ['--connection' => 'work_once', '--queue' => 'default'])->assertExitCode(0);

        $this->assertSame(2, self::$ran);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function testOnlyTheGivenQueuesAreDrained(): void
    {
        dispatch(function () {
            WorkOnceCommandTest::$ran++;
        })->onConnection('work_once')->onQueue('builder');

        $this->artisan('ulams:tenant:work-once', ['--connection' => 'work_once', '--queue' => 'default'])->assertExitCode(0);

        $this->assertSame(0, self::$ran);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'builder')->count());
    }

    public function testEmptyQueueIsNotAnError(): void
    {
        $this->artisan('ulams:tenant:work-once', ['--connection' => 'work_once'])->assertExitCode(0);
    }

    public function testSchedulerTickRunsOnlyWithTheScheduleOption(): void
    {
        $ticks = 0;
        $this->app->make(Schedule::class)->call(function () use (&$ticks) {
            $ticks++;
        })->everyMinute();

        $this->artisan('ulams:tenant:work-once', ['--connection' => 'work_once'])->assertExitCode(0);
        $this->assertSame(0, $ticks);

        $this->artisan('ulams:tenant:work-once', ['--connection' => 'work_once', '--schedule' => true])->assertExitCode(0);
        $this->assertSame(1, $ticks);
    }
}
