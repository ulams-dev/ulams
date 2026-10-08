<?php

namespace EscolaLms\CourseAccess\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\CourseAccess\Tests\TestCase;

class CourseAccessEnquiryDeleteApiTest extends TestCase
{
    use CreatesUsers;

    private $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseAccessPermissionSeeder::class);
        $this->student = $this->makeStudent();
    }

    public function testCourseAccessEnquiryDeleteUnauthorized(): void
    {
        $courseAccessEnquiry = CourseAccessEnquiry::factory()->create();

        $this->deleteJson('api/course-access-enquiries/' . $courseAccessEnquiry->getKey())
            ->assertUnauthorized();

        $this->actingAs($this->student)
            ->deleteJson('api/course-access-enquiries/' . $courseAccessEnquiry->getKey())
            ->assertUnauthorized();
    }

    public function testCourseAccessEnquiryDelete(): void
    {
        $courseAccessEnquiry = CourseAccessEnquiry::factory()
            ->state(['user_id' => $this->student->getKey()])
            ->create();

        $this->actingAs($this->student, 'api')
            ->deleteJson('api/course-access-enquiries/' . $courseAccessEnquiry->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('course_access_enquiries', [
            'id' => $courseAccessEnquiry->getKey(),
        ]);
    }
}
