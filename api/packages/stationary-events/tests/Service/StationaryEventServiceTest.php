<?php

namespace Ulams\StationaryEvents\Tests\Service;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\StationaryEvents\Events\StationaryEventAssigned;
use Ulams\StationaryEvents\Models\StationaryEvent;
use Ulams\StationaryEvents\Services\Contracts\StationaryEventServiceContract;
use Ulams\StationaryEvents\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

class StationaryEventServiceTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private StationaryEventServiceContract $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StationaryEventServiceContract::class);
        $this->stationaryEvent = StationaryEvent::factory()->create()->first();
    }

    public function testAddAccessForUsersTest(): void
    {
        Event::fake([StationaryEventAssigned::class]);
        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();

        $this->service->addAccessForUsers($this->stationaryEvent, [$student1->getKey(), $student2->getKey()]);
        $this->assertCount(2, $this->stationaryEvent->users);

        Event::assertDispatchedTimes(StationaryEventAssigned::class, 2);
    }
}
