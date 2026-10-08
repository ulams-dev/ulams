<?php

namespace EscolaLms\Tasks\Tests\Feature;

use EscolaLms\Tasks\Events\TaskOverdueEvent;
use EscolaLms\Tasks\Jobs\OverdueTaskJob;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class OverdueTaskJobTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testOverdueTaskJob()
    {
        Task::factory()
            ->count(4)
            ->create([
                'due_date' => Carbon::now()->subDays(4)
            ]);
        Task::factory()
            ->count(10)
            ->create([
                'due_date' => Carbon::now()->subDays(10)
            ]);
        Task::factory()
            ->count(5)
            ->create([
                'due_date' => Carbon::now()->addDays(5)
            ]);
        Task::factory()
            ->count(15)
            ->create(['due_date' => null]);

        Event::fake();
        (new OverdueTaskJob(0, 5))->handle();
        Event::assertDispatchedTimes(TaskOverdueEvent::class, 4);

        Event::fake();
        (new OverdueTaskJob(6, 11))->handle();
        Event::assertDispatchedTimes(TaskOverdueEvent::class, 10);
    }
}
