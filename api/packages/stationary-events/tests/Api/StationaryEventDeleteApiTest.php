<?php

namespace EscolaLms\StationaryEvents\Tests\Api;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\StationaryEvents\Database\Seeders\StationaryEventPermissionSeeder;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
use EscolaLms\StationaryEvents\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class StationaryEventDeleteApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StationaryEventPermissionSeeder::class);
        $this->user = $this->makeInstructor();
        $this->stationaryEvent = StationaryEvent::factory()
            ->has(Category::factory())
            ->create();
    }

    public function testStationaryEventDeleteUnauthorized(): void
    {
        $this->deleteJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey())
            ->assertUnauthorized();
    }

    public function testStationaryEventDelete(): void
    {
        $this->stationaryEvent->users()->sync([$this->makeStudent()->getKey()]);
        $this->stationaryEvent->authors()->sync([$this->makeInstructor()->getKey()]);

        $this->actingAs($this->user, 'api')
            ->deleteJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey())
            ->assertOk()
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseMissing('stationary_events', [
            'id' => $this->stationaryEvent->getKey(),
        ]);
    }

    public function testStationaryEventDeleteNotFound(): void
    {
        $this->stationaryEvent->delete();

        $this->actingAs($this->user, 'api')
            ->deleteJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey())
            ->assertNotFound();
    }
}
