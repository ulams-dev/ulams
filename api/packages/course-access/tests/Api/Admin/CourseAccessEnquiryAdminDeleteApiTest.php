<?php

namespace EscolaLms\CourseAccess\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\CourseAccess\Tests\TestCase;

class CourseAccessEnquiryAdminDeleteApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseAccessPermissionSeeder::class);
    }

    public function testAdminDeleteCourseAccessEnquiryUnauthorized(): void
    {
        $courseAccessEnquiry = CourseAccessEnquiry::factory()->create();

        $this->deleteJson('api/admin/course-access-enquiries/' . $courseAccessEnquiry->getKey())
            ->assertUnauthorized();
    }

    public function testAdminDeleteCourseAccessEnquiry(): void
    {
        $admin = $this->makeAdmin();
        $courseAccessEnquiry = CourseAccessEnquiry::factory()->create();

        $this->actingAs($admin, 'api')
            ->deleteJson('api/admin/course-access-enquiries/' . $courseAccessEnquiry->getKey())
            ->assertOk();
    }
}
