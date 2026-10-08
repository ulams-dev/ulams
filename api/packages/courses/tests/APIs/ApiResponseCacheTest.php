<?php

namespace Ulams\Courses\Tests\APIs;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\Models\User;
use Ulams\Courses\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\ResponseCache\Events\CacheMissed;
use Spatie\ResponseCache\Events\ResponseCacheHit;

class ApiResponseCacheTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);
        $this->course = Course::factory()->create([
            'status' => CourseStatusEnum::PUBLISHED,
        ]);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
        $this->course->authors()->sync($this->user);

        $this->student = User::factory()->create();
        $this->course->users()->sync([$this->student->getKey()]);

        $this->lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        $this->topics = Topic::factory(2)->create(['lesson_id' => $this->lesson->getKey(), 'active' => true]);
        foreach ($this->topics as $topic) {
            CourseProgress::create([
                'user_id' => $this->student->getKey(),
                'topic_id' => $topic->getKey(),
                'status' => 1
            ]);
        }
    }

    public function testCacheCourseResponse(): void
    {
        Event::fake([CacheMissed::class, ResponseCacheHit::class]);

        $this->actingAs($this->student, 'api')->getJson('/api/courses/' . $this->course->getKey())
            ->assertStatus(200);

        Event::assertDispatched(CacheMissed::class);
        Event::assertNotDispatched(ResponseCacheHit::class);

        $this->actingAs($this->student, 'api')->getJson('/api/courses/' . $this->course->getKey())
            ->assertStatus(200);

        Event::assertDispatched(ResponseCacheHit::class);
        Event::assertDispatchedTimes(CacheMissed::class);
    }

    public function testCacheCoursesList(): void
    {
        Event::fake([CacheMissed::class, ResponseCacheHit::class]);
        Course::factory(2)->create(['status' => CourseStatusEnum::PUBLISHED]);

        $this->getJson('/api/courses')
            ->assertStatus(200);

        Event::assertDispatched(CacheMissed::class);
        Event::assertNotDispatched(ResponseCacheHit::class);

        $this->getJson('/api/courses')
            ->assertStatus(200);

        Event::assertDispatched(ResponseCacheHit::class);
        Event::assertDispatchedTimes(CacheMissed::class);
    }

    public function testCacheCourseProgram(): void
    {
        Event::fake([CacheMissed::class, ResponseCacheHit::class]);

        $this->actingAs($this->student, 'api')->getJson('/api/courses/' . $this->course->getKey() . '/program')
            ->assertStatus(200);

        Event::assertDispatched(CacheMissed::class);
        Event::assertNotDispatched(ResponseCacheHit::class);

        $this->actingAs($this->student, 'api')->getJson('/api/courses/' . $this->course->getKey() . '/program')
            ->assertStatus(200);

        Event::assertDispatched(ResponseCacheHit::class);
        Event::assertDispatchedTimes(CacheMissed::class);
    }

    public function testCacheCourseProgress(): void
    {
        Event::fake([CacheMissed::class, ResponseCacheHit::class]);

        $this->actingAs($this->student, 'api')->getJson('/api/courses/progress')
            ->assertStatus(200);

        Event::assertDispatched(CacheMissed::class);
        Event::assertNotDispatched(ResponseCacheHit::class);

        $this->actingAs($this->student, 'api')->getJson('/api/courses/progress')
            ->assertStatus(200);

        Event::assertDispatched(ResponseCacheHit::class);
        Event::assertDispatchedTimes(CacheMissed::class);
    }

    public function testCacheAdminCourseProgram(): void
    {
        Event::fake([CacheMissed::class, ResponseCacheHit::class]);

        $this->actingAs($this->user, 'api')->getJson('/api/admin/courses/' . $this->course->getKey() . '/program')
            ->assertStatus(200);

        Event::assertDispatched(CacheMissed::class);
        Event::assertNotDispatched(ResponseCacheHit::class);

        $this->actingAs($this->user, 'api')->getJson('/api/admin/courses/' . $this->course->getKey() . '/program')
            ->assertStatus(200);

        Event::assertDispatched(ResponseCacheHit::class);
        Event::assertDispatchedTimes(CacheMissed::class);
    }

    public function testCacheAdminTopicResources(): void
    {
        Event::fake([CacheMissed::class, ResponseCacheHit::class]);
        Storage::fake('local');

        $file = UploadedFile::fake()->create('test.pdf');
        $topic = Topic::factory()->create(['lesson_id' => $this->lesson->getKey(), 'active' => true]);

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/topics/' . $topic->getKey() . '/resources',
            [
                'resource' => $file,
            ]
        )->assertStatus(201);

        $this->actingAs($this->user, 'api')->getJson('/api/admin/topics/' . $topic->getKey() . '/resources')
            ->assertStatus(200);

        Event::assertDispatched(CacheMissed::class);
        Event::assertNotDispatched(ResponseCacheHit::class);

        $this->actingAs($this->user, 'api')->getJson('/api/admin/topics/' . $topic->getKey() . '/resources')
            ->assertStatus(200);

        Event::assertDispatched(ResponseCacheHit::class);
        Event::assertDispatchedTimes(CacheMissed::class);
    }
}
