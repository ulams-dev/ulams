<?php

namespace Ulams\CourseAccess\Tests\Api\Admin;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use Ulams\CourseAccess\Enum\EnquiryStatusEnum;
use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Ulams\CourseAccess\Tests\TestCase;

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
