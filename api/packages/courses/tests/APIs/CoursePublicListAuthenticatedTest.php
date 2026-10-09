<?php

namespace Ulams\Courses\Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Tests\TestCase;

/**
 * The course list is a public catalogue: a logged-in student gets the same courses as an
 * anonymous visitor (published and findable), not a 403.
 */
class CoursePublicListAuthenticatedTest extends TestCase
{
    use DatabaseTransactions;

    public function testStudentSeesThePublicCatalogue(): void
    {
        $this->seed(CoursesPermissionSeeder::class);
        $student = config('auth.providers.users.model')::factory()->create();
        $student->guard_name = 'api';
        $student->assignRole('student');

        $published = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'findable' => true]);
        $draft = Course::factory()->create(['status' => CourseStatusEnum::DRAFT, 'findable' => true]);
        $hidden = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'findable' => false]);

        $asStudent = $this->actingAs($student, 'api')->getJson('/api/courses?per_page=1000')->assertOk();
        $ids = collect($asStudent->json('data'))->pluck('id');
        $this->assertContains($published->getKey(), $ids);
        $this->assertNotContains($draft->getKey(), $ids);
        $this->assertNotContains($hidden->getKey(), $ids);

        // the same courses as an anonymous visitor
        $this->app['auth']->forgetGuards();
        $anonymous = collect($this->getJson('/api/courses?per_page=1000')->assertOk()->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing($anonymous->all(), $ids->all());
    }

    public function testStudentCannotListDraftsByAskingForThem(): void
    {
        $this->seed(CoursesPermissionSeeder::class);
        $student = config('auth.providers.users.model')::factory()->create();
        $student->guard_name = 'api';
        $student->assignRole('student');
        $draft = Course::factory()->create(['status' => CourseStatusEnum::DRAFT, 'findable' => true]);

        $ids = collect($this->actingAs($student, 'api')->getJson('/api/courses?per_page=1000&status=draft')->assertOk()->json('data'))->pluck('id');

        $this->assertNotContains($draft->getKey(), $ids);
    }
}
