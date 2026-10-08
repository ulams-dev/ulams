<?php

namespace Ulams\CourseAccess\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Ulams\CourseAccess\Tests\TestCase;

class CourseAccessEnquiryListApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseAccessPermissionSeeder::class);
    }

    public function testCourseAccessEnquiryListUnauthorized(): void
    {
        $this->getJson('api/course-access-enquiries')
            ->assertUnauthorized();
    }

    public function testCourseAccessEnquiryList(): void
    {
        $student = $this->makeStudent();

        CourseAccessEnquiry::factory()
            ->state(['user_id' => $student->getKey()])
            ->count(4)
            ->create();

        CourseAccessEnquiry::factory()->count(2)->create();

        $this->actingAs($student, 'api')
            ->getJson('api/course-access-enquiries')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'created_at',
                    'course' => [
                        'id',
                        'name',
                    ],
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'status',
                    'data',
                ]]
            ]);
    }
}
