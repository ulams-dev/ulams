<?php

namespace EscolaLms\CourseAccess\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use EscolaLms\CourseAccess\Enum\EnquiryStatusEnum;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\CourseAccess\Tests\TestCase;

class CourseAccessEnquiryAdminApproveApiTest extends TestCase
{
    use CreatesUsers;

    private $courseAccessEnquiry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseAccessPermissionSeeder::class);
        $this->courseAccessEnquiry = CourseAccessEnquiry::factory()->create();
    }

    public function testCourseAccessEnquiryAdminApproveUnauthorized(): void
    {
        $this->postJson('api/admin/course-access-enquiries/approve/' . $this->courseAccessEnquiry->getKey())
            ->assertUnauthorized();
    }

    public function testCourseAccessEnquiryAdminApprove(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('api/admin/course-access-enquiries/approve/' . $this->courseAccessEnquiry->getKey())
            ->assertOk();

        $this->courseAccessEnquiry->refresh();
        $this->assertEquals(EnquiryStatusEnum::APPROVED, $this->courseAccessEnquiry->status);
    }
}
