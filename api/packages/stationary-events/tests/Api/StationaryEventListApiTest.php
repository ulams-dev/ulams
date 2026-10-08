<?php

namespace EscolaLms\StationaryEvents\Tests\Api;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\StationaryEvents\Database\Seeders\StationaryEventPermissionSeeder;
use EscolaLms\StationaryEvents\Enum\StationaryEventStatusEnum;
use EscolaLms\StationaryEvents\Http\Resources\UserResource;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
use EscolaLms\StationaryEvents\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;

class StationaryEventListApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StationaryEventPermissionSeeder::class);
        $this->user = $this->makeInstructor();
    }

    public function testAdminStationaryEventListUnauthorized(): void
    {
        $this->getJson('api/admin/stationary-events')->assertUnauthorized();
    }

    public function testStationaryEventsAdminListWithFilter(): void
    {
        $stationaryEvent = StationaryEvent::factory()->create();
        $student = $this->makeStudent();
        $stationaryEvent->users()->sync($student);
        $author = $this->makeInstructor();
        $stationaryEvent->authors()->sync($author);

        $this->response = $this->actingAs($this->user, 'api')
            ->getJson('api/admin/stationary-events?name=' . $stationaryEvent->name)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $stationaryEvent->getKey(),
                'name' => $stationaryEvent->name,
            ]);

        $this->response->assertJsonFragment([
            'authors' => [
                UserResource::make($author)->toArray(null),
            ],
            'users' => [
                UserResource::make($student)->toArray(null),
            ]
        ]);

        $this->response = $this->actingAs($this->user, 'api')
            ->getJson('api/admin/stationary-events?status=' . $stationaryEvent->status)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $stationaryEvent->getKey(),
                'name' => $stationaryEvent->name,
            ]);
    }

    public function testStationaryEventsAdminListFilterByCategories(): void
    {
        $category = Category::factory()->create();
        $category2 = Category::factory()->create();
        $stationaryEvent = StationaryEvent::factory()->create([
            'status' => StationaryEventStatusEnum::PUBLISHED
        ]);

        $stationaryEvent2 = StationaryEvent::factory([
            'status' => StationaryEventStatusEnum::PUBLISHED
        ])->create();

        $stationaryEvent->categories()->save($category);
        $stationaryEvent2->categories()->save($category2);

        $this->response = $this->actingAs($this->user, 'api')
            ->json(
                'GET',
                'api/admin/stationary-events',
                [
                    'categories' => [
                        $category->getKey(),
                        $category2->getKey(),
                    ]
                ]
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $stationaryEvent->getKey(),
                'name' => $stationaryEvent->name,
            ])
            ->assertJsonFragment([
                'id' => $stationaryEvent2->getKey(),
                'name' => $stationaryEvent2->name,
            ]);
    }

    public function testStationaryEventsPublicListWithFilter(): void
    {
        $stationaryEvent = StationaryEvent::factory()->create([
            'status' => StationaryEventStatusEnum::PUBLISHED
        ]);

        $stationaryEvent2 = StationaryEvent::factory([
            'started_at' => now()->modify('-1 days'),
            'status' => StationaryEventStatusEnum::PUBLISHED
        ])->create();

        $student = $this->makeStudent();
        $stationaryEvent->users()->sync($student);
        $author = $this->makeInstructor();
        $stationaryEvent->authors()->sync($author);

        $this->response = $this->getJson('api/stationary-events?name=' . $stationaryEvent->name)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $stationaryEvent->getKey(),
                'name' => $stationaryEvent->name,
            ])
            ->assertJsonMissing([
                'id' => $stationaryEvent2->getKey(),
                'name' => $stationaryEvent2->name,
            ])
            ->assertJsonFragment([
                'authors' => [
                    UserResource::make($author)->toArray(null),
                ],
            ])->assertJsonMissing([
                'users' => [
                    UserResource::make($student)->toArray(null),
                ]
            ]);
    }

    public function testStationaryEventsPublicListFilterByCategories(): void
    {
        $category = Category::factory()->create();
        $category2 = Category::factory()->create();
        $stationaryEvent = StationaryEvent::factory()->create([
            'status' => StationaryEventStatusEnum::PUBLISHED
        ]);

        $stationaryEvent2 = StationaryEvent::factory([
            'status' => StationaryEventStatusEnum::PUBLISHED
        ])->create();

        $stationaryEvent->categories()->save($category);
        $stationaryEvent2->categories()->save($category2);

        $student = $this->makeStudent();
        $stationaryEvent->users()->sync($student);
        $author = $this->makeInstructor();
        $stationaryEvent->authors()->sync($author);

        $this->response = $this->json(
            'GET',
            'api/stationary-events',
            [
                'categories' => [
                    $category->getKey(),
                    $category2->getKey(),
                ]
            ]
        )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $stationaryEvent->getKey(),
                'name' => $stationaryEvent->name,
            ])
            ->assertJsonFragment([
                'id' => $stationaryEvent2->getKey(),
                'name' => $stationaryEvent2->name,
            ]);
    }

    public function testStationaryEventsListForCurrentUser(): void
    {
        $student = $this->makeStudent();
        $stationaryEvent = StationaryEvent::factory()->create();
        $stationaryEvent2 = StationaryEvent::factory()->create();
        $stationaryEvent3 = StationaryEvent::factory()->create();
        $stationaryEvent->users()->sync($student->getKey());

        $this->travel(5)->days();

        $stationaryEvent2->users()->sync($student->getKey());

        $this->travel(5)->days();

        $stationaryEvent3->users()->sync($student->getKey());

        $this->response = $this->actingAs($student, 'api')->getJson('api/stationary-events/me')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertTrue($this->response->json('data.0.id') === $stationaryEvent3->getKey());
        $this->assertTrue($this->response->json('data.1.id') === $stationaryEvent2->getKey());
        $this->assertTrue($this->response->json('data.2.id') === $stationaryEvent->getKey());

        $this->response = $this->actingAs($student, 'api')->getJson('api/stationary-events/me?name=' . $stationaryEvent->name)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $stationaryEvent->getKey(),
                'name' => $stationaryEvent->name,
            ]);

    }
}
