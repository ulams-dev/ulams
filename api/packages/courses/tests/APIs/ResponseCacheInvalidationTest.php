<?php

namespace Ulams\Courses\Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Spatie\ResponseCache\Events\CacheMissedEvent;
use Spatie\ResponseCache\Events\ResponseCacheHitEvent;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Support\ResponseCacheTags;
use Ulams\Courses\Tests\Models\User;
use Ulams\Courses\Tests\TestCase;

/**
 * Learner activity (progress, time tracking) must not empty the catalogue response cache;
 * catalogue writes must.
 */
class ResponseCacheInvalidationTest extends TestCase
{
    use DatabaseTransactions;

    private Course $course;
    private Topic $topic;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'responsecache.cache.store' => 'array', 'responsecache.cache.tag' => '']);
        $this->seed(CoursesPermissionSeeder::class);
        $this->course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $this->topic = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $this->student = User::factory()->create();
        $this->student->guard_name = 'api';
        $this->student->assignRole('student');
        $this->course->users()->sync([$this->student->getKey()]);
    }

    public function testActivityModelsClearOnlyProgress(): void
    {
        $this->assertSame([ResponseCacheTags::PROGRESS], ResponseCacheTags::tagsFor(CourseProgress::class));
        $this->assertSame([], ResponseCacheTags::tagsFor('Ulams\\Lrs\\Models\\Statement'));
        $this->assertSame([ResponseCacheTags::CATALOGUE, ResponseCacheTags::PROGRESS], ResponseCacheTags::tagsFor(Course::class));
    }

    public function testProgressWritesKeepTheCatalogueCached(): void
    {
        $this->getJson('/api/courses')->assertOk();
        $this->actingAs($this->student, 'api')->getJson('/api/courses/progress')->assertOk();
        $this->app['auth']->forgetGuards();
        $r = $this->getJson('/api/courses');

        Event::fake([CacheMissedEvent::class, ResponseCacheHitEvent::class]);
        CourseProgress::updateOrCreate(['user_id' => $this->student->getKey(), 'topic_id' => $this->topic->getKey()], ['status' => 1]);

        // anonymous again (the public catalogue)
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/courses')->assertOk();
        Event::assertDispatchedTimes(ResponseCacheHitEvent::class, 1);

        // the progress list is re-read
        $this->actingAs($this->student, 'api')->getJson('/api/courses/progress')->assertOk();
        Event::assertDispatchedTimes(CacheMissedEvent::class, 1);
    }

    public function testCatalogueWritesClearTheCatalogue(): void
    {
        $this->getJson('/api/courses')->assertOk();

        Event::fake([CacheMissedEvent::class, ResponseCacheHitEvent::class]);
        $this->course->update(['title' => 'Renamed course']);

        $this->getJson('/api/courses')->assertOk()->assertJsonFragment(['title' => 'Renamed course']);
        Event::assertNotDispatched(ResponseCacheHitEvent::class);
        Event::assertDispatched(CacheMissedEvent::class);
    }
}
