<?php

namespace EscolaLms\StationaryEvents\Tests\Api;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\StationaryEvents\Database\Seeders\StationaryEventPermissionSeeder;
use EscolaLms\StationaryEvents\Enum\ConstantEnum;
use EscolaLms\StationaryEvents\Events\StationaryEventAuthorAssigned;
use EscolaLms\StationaryEvents\Events\StationaryEventAuthorUnassigned;
use EscolaLms\StationaryEvents\Http\Resources\UserResource;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
use EscolaLms\StationaryEvents\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

class StationaryEventUpdateApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StationaryEventPermissionSeeder::class);
        $this->user = $this->makeInstructor();
        $this->stationaryEvent = StationaryEvent::factory()
            ->has(Category::factory())
            ->create();
    }

    public function testStationaryEventUpdateUnauthorized(): void
    {
        $this->putJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey())
            ->assertUnauthorized();
    }

    public function testStationaryEventUpdate(): void
    {
        Storage::fake();

        $stationaryEvent = StationaryEvent::factory()->make()->toArray();

        $this->response = $this->actingAs($this->user, 'api')
            ->putJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey(),
                array_merge($stationaryEvent, [
                    'image' => UploadedFile::fake()->image('image.jpg'),
                    'agenda' => json_encode([
                        'key1' => $this->faker->text,
                        'key2' => [
                            'key3' => $this->faker->text,
                        ],
                    ]),
                ])
            )->assertOk();

        $this->assertApiResponse($stationaryEvent);
        $data = $this->response->getData()->data;
        Storage::exists($data->image_path);
    }

    public function testStationaryEventPartialUpdate(): void
    {
        $stationaryEvent = StationaryEvent::factory()->create([
            'name' => 'Title',
            'place' => 'Place',
            'max_participants' => 4,
        ]);

        $this->response = $this->actingAs($this->user, 'api')
            ->putJson('api/admin/stationary-events/' . $stationaryEvent->getKey(), [
                'name' => 'New title',
            ])
            ->assertOk();

        $this->response->assertJsonFragment([
            'name' => 'New title',
            'place' => 'Place',
            'max_participants' => 4,
        ]);
    }

    public function testStationaryEventUpdateOnlyImage(): void
    {
        Storage::fake();

        $this->response = $this->actingAs($this->user, 'api')
            ->putJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey(), [
                'image' => UploadedFile::fake()->image('image.jpg')
            ])->assertOk();

        $this->assertApiResponse($this->stationaryEvent->toArray());
        $data = $this->response->getData()->data;
        Storage::exists($data->image_path);
    }

    public function testStationaryEventRemoveImage(): void
    {
        $this->response = $this->actingAs($this->user, 'api')
            ->putJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey(), [
                'image_path' => '',
            ])->assertOk();

        $this->assertApiResponse($this->stationaryEvent->toArray());
        $data = $this->response->getData()->data;
        $this->assertNull($data->image_path);
        $this->assertNull($data->image_url);
    }

    public function testStationaryEventUpdateAuthorMustByTutor(): void
    {
        $stationaryEvent = StationaryEvent::factory()->make()->toArray();
        $student = $this->makeStudent();
        $stationaryEvent['authors'] = [$student->getKey()];

        $response = $this->actingAs($this->user, 'api')->putJson(
            'api/admin/stationary-events/' . $this->stationaryEvent->getKey(),
            $stationaryEvent
        );

        $response->assertJsonValidationErrors(['authors.0']);
    }

    public function testStationaryEventUpdateNotFound(): void
    {
        $stationaryEvent = StationaryEvent::factory()->make()->toArray();
        $this->stationaryEvent->delete();

        $this->response = $this->actingAs($this->user, 'api')
            ->putJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey(), $stationaryEvent)
            ->assertNotFound();
    }

    public function testStationaryEventCreateWithAuthor(): void
    {
        Event::fake([StationaryEventAuthorAssigned::class, StationaryEventAuthorUnassigned::class]);

        $this->stationaryEvent->authors()->sync($this->user->getKey());
        $stationaryEvent = StationaryEvent::factory()->make()->toArray();
        $tutor = $this->makeInstructor();
        $stationaryEvent['authors'] = [$tutor->getKey()];

        $this->response = $this->actingAs($this->user, 'api')->putJson(
            'api/admin/stationary-events/' . $this->stationaryEvent->getKey(),
            $stationaryEvent
        )->assertOk();

        $this->response->assertJsonFragment([
            'authors' => [
                UserResource::make($tutor)->toArray(null),
            ],
        ]);

        Event::assertDispatched(StationaryEventAuthorAssigned::class);
        Event::assertDispatched(StationaryEventAuthorUnassigned::class);
    }

    public function testStationaryEventUpdateWithCategories(): void
    {
        $categories = Category::factory()->count(2)->create()->pluck('id')->toArray();
        $stationaryEvent = StationaryEvent::factory()->make()->toArray();
        $stationaryEvent['categories'] = $categories;

        $this->response = $this->actingAs($this->user, 'api')->putJson(
            'api/admin/stationary-events/' . $this->stationaryEvent->getKey(),
            $stationaryEvent
        )->assertOk();

        $this->response->assertJsonFragment([
            'name' => $stationaryEvent['name'],
            'description' => $stationaryEvent['description'],
            'started_at' => $stationaryEvent['started_at'],
            'finished_at' => $stationaryEvent['finished_at'],
            'place' => $stationaryEvent['place'],
            'max_participants' => $stationaryEvent['max_participants'],
        ]);
        $this->response->assertJsonCount(2, 'data.categories');
        $this->response->assertJson(fn(AssertableJson $json) => $json->has(
            'data', fn($json) => $json->has(
            'categories', fn(AssertableJson $json) => $json->each(
            fn(AssertableJson $json) => $json->where('id', fn($json) => in_array($json, $categories))->etc()
        )->etc()
        )->etc()
        )->etc());
    }

    public function testStationaryEventUpdateRemoveCategories(): void
    {
        $stationaryEvent = StationaryEvent::factory()->make()->toArray();
        $stationaryEvent['categories'] = [];

        $this->assertCount(1, $this->stationaryEvent->categories);

        $this->response = $this->actingAs($this->user, 'api')->putJson(
            'api/admin/stationary-events/' . $this->stationaryEvent->getKey(),
            $stationaryEvent
        )->assertOk();

        $this->response->assertJsonCount(0, 'data.categories');
    }

    public function testUpdateStationaryEventImageFromReusableFile(): void
    {
        Storage::fake();
        $directoryPath = ConstantEnum::DIRECTORY . "/{$this->stationaryEvent->getKey()}/images";
        UploadedFile::fake()->image('image.jpg')->storeAs($directoryPath, 'image.jpg');
        $imagePath = "{$directoryPath}/image.jpg";

        $this->response = $this->actingAs($this->user, 'api')
            ->putJson('api/admin/stationary-events/' . $this->stationaryEvent->getKey(), [
                'image' => $imagePath,
            ])->assertOk();

        $data = $this->response->getData()->data;
        Storage::assertExists($data->image_path);
    }
}
