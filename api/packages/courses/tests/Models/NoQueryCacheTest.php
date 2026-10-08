<?php

namespace Ulams\Courses\Tests\Models;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\TestCase;

/**
 * Course, Lesson, Topic, TopicResource and topic contents used to cache every query for an hour
 * (rennokki/laravel-eloquent-query-cache), invalidated only by Eloquent model events. Writes that
 * bypass those events (query builder updates, other tables) were served stale. Reads now always
 * hit the database.
 */
class NoQueryCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function testCourseReadAfterQueryBuilderUpdateIsFresh(): void
    {
        $course = Course::factory()->create(['title' => 'Before']);
        $this->assertSame('Before', Course::query()->find($course->getKey())->title);

        DB::table($course->getTable())->where('id', $course->getKey())->update(['title' => 'After']);

        $this->assertSame('After', Course::query()->find($course->getKey())->title);
    }

    public function testLessonAndTopicCountsSeeRowsInsertedWithoutModelEvents(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey()]);

        $this->assertSame(1, Topic::query()->where('lesson_id', $lesson->getKey())->count());

        Topic::withoutEvents(fn () => Topic::factory()->create(['lesson_id' => $lesson->getKey()]));

        $this->assertSame(2, Topic::query()->where('lesson_id', $lesson->getKey())->count());
        $this->assertNotNull(Topic::query()->find($topic->getKey()));
    }

    public function testModelsNoLongerUseTheQueryCacheTrait(): void
    {
        foreach ([Course::class, Lesson::class, Topic::class] as $model) {
            $this->assertArrayNotHasKey(
                'Rennokki\\QueryCache\\Traits\\QueryCacheable',
                class_uses_recursive($model),
                $model
            );
        }
    }
}
