<?php

namespace EscolaLms\StationaryEvents\Tests\Service;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\StationaryEvents\Events\StationaryEventAssigned;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
use EscolaLms\StationaryEvents\Services\Contracts\StationaryEventServiceContract;
use EscolaLms\StationaryEvents\Tests\TestCase;
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
