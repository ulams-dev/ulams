<?php

namespace EscolaLms\CoursesImportExport\Tests\APIs;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder;
use EscolaLms\CoursesImportExport\Jobs\CloneCourse;
use EscolaLms\CoursesImportExport\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class CourseCloneApiTest extends TestCase
{
    use CreatesUsers,DatabaseTransactions, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesExportImportPermissionSeeder::class);
    }

    public function testCloneCourse(): void
    {
        Event::fake();
        Queue::fake();

        $admin = $this->makeAdmin();
        $course = Course::factory()->create();

        $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/courses/' . $course->getKey() . '/clone')
            ->assertOk();

        Queue::assertPushed(CloneCourse::class);
    }

    public function testCloneCourseAnonymousUser(): void
    {
        Event::fake();
        Queue::fake();

        $course = Course::factory()->create();

        $this->json('GET', '/api/admin/courses/' . $course->getKey() . '/clone')
            ->assertUnauthorized();

        Queue::assertNotPushed(CloneCourse::class);
    }

    public function testCloneNonExistentCourse(): void
    {
        Event::fake();
        Queue::fake();

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->json('GET', '/api/admin/courses/-1/clone')
            ->assertNotFound();

        Queue::assertNotPushed(CloneCourse::class);
    }
}
