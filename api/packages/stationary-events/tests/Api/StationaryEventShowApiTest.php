<?php

namespace EscolaLms\StationaryEvents\Tests\Api;

use Carbon\Carbon;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\StationaryEvents\Database\Seeders\StationaryEventPermissionSeeder;
use EscolaLms\StationaryEvents\Enum\StationaryEventStatusEnum;
use EscolaLms\StationaryEvents\Http\Resources\UserResource;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
use EscolaLms\StationaryEvents\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class StationaryEventShowApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StationaryEventPermissionSeeder::class);
        $this->user = $this->makeInstructor();
        $this->stationaryEvent = StationaryEvent::factory()->create([
            'status' => StationaryEventStatusEnum::PUBLISHED
        ]);
        $this->stationaryEvent->authors()->sync($this->user->getKey());
    }

    public function testStationaryEventShowUnauthorized(): void
    {
        $this->getJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey())
            ->assertUnauthorized();
    }

    public function testStationaryEventShow(): void
    {
        $this->response = $this->actingAs($this->user, 'api')
            ->getJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey())
            ->assertOk();

        $this->assertApiResponse($this->stationaryEvent->toArray());

        $this->response->assertJsonFragment([
            'authors' => [
                UserResource::make($this->user)->toArray(null),
            ],
        ]);
    }

    public function testStationaryEventShowNotFound(): void
    {
        $this->response = $this->actingAs($this->user, 'api')->json('GET', 'api/admin/stationary-events/0');
        $this->response->assertStatus(422);
    }

    public function testStationaryEventPublicShow(): void
    {
        $this->response = $this->getJson('api/stationary-events/' . $this->stationaryEvent->getKey())
            ->assertOk();

        $this->assertApiResponse($this->stationaryEvent->toArray());

        $this->response->assertJsonFragment([
            'authors' => [
                UserResource::make($this->user)->toArray(null),
            ],
        ]);

        $this->response->assertJsonMissing([
            'users' => []
        ]);
    }

    public function testStationaryEventUnpublished(): void
    {
        $stationaryEvent = StationaryEvent::factory()->create([
            'status' => StationaryEventStatusEnum::DRAFT
        ]);

        $this->response = $this->getJson('api/stationary-events/' . $stationaryEvent->getKey())
            ->assertStatus(400)
            ->assertJsonFragment([
                'success' => false,
                'message' => __('Stationary events is unpublished'),
            ]);
    }


    public function testStationaryEventIsComing(): void
    {
        $stationaryEvent = StationaryEvent::factory()->create([
            'started_at' => Carbon::now()->addDay(),
            'finished_at' => Carbon::now()->addDay()->addHour(),
        ]);

        $this->response = $this->actingAs($this->user, 'api')
            ->getJson('api/admin/stationary-events/' . $stationaryEvent->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'in_coming' => true,
                'is_ended' => false,
                'is_started' => false,
            ]);
    }

    public function testStationaryEventIsEnded(): void
    {
        $stationaryEvent = StationaryEvent::factory()->create([
            'started_at' => Carbon::now()->subDay()->subHour(),
            'finished_at' => Carbon::now()->subDay(),
        ]);

        $this->response = $this->actingAs($this->user, 'api')
            ->getJson('api/admin/stationary-events/' . $stationaryEvent->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'in_coming' => false,
                'is_ended' => true,
                'is_started' => false,
            ]);
    }

    public function testStationaryEventIsStarted(): void
    {
        $stationaryEvent = StationaryEvent::factory()->create([
            'started_at' => Carbon::now()->subHour(),
            'finished_at' => Carbon::now()->addHour(),
        ]);

        $this->response = $this->actingAs($this->user, 'api')
            ->getJson('api/admin/stationary-events/' . $stationaryEvent->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'in_coming' => false,
                'is_ended' => false,
                'is_started' => true,
            ]);
    }
}
