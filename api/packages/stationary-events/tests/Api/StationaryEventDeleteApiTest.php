<?php

namespace Ulams\StationaryEvents\Tests\Api;

use Ulams\Categories\Models\Category;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\StationaryEvents\Database\Seeders\StationaryEventPermissionSeeder;
use Ulams\StationaryEvents\Models\StationaryEvent;
use Ulams\StationaryEvents\Tests\TestCase;
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
