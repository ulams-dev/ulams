<?php

use Ulams\Courses\Models\Course;
use Ulams\Courses\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Spatie\ResponseCache\Events\ClearedResponseCacheEvent;

class ResponseCacheTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    public function testClearResponseCacheAfterCreated(): void
    {
        Event::fake([ClearedResponseCacheEvent::class]);
        Course::factory()->create();
        Event::assertDispatched(ClearedResponseCacheEvent::class);
    }

    public function testClearResponseCacheAfterUpdated(): void
    {
        $course = Course::factory()->create();
        Event::fake([ClearedResponseCacheEvent::class]);

        $course->update([
            'title' => $this->faker->title,
        ]);

        Event::assertDispatched(ClearedResponseCacheEvent::class);
    }

    public function testClearResponseCacheAfterDeleted(): void
    {
        $course = Course::factory()->create();
        Event::fake([ClearedResponseCacheEvent::class]);
        $course->delete();
        Event::assertDispatched(ClearedResponseCacheEvent::class);
    }
}