<?php

namespace EscolaLms\CourseAccess\Tests\Api;

use EscolaLms\Auth\Enums\SettingStatusEnum;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use EscolaLms\CourseAccess\Enum\CourseAccessPermissionEnum;
use EscolaLms\CourseAccess\Enum\EnquiryStatusEnum;
use EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider;
use EscolaLms\CourseAccess\Events\CourseAccessEnquiryAdminCreatedEvent;
use EscolaLms\CourseAccess\Events\CourseAccessEnquiryStudentCreatedEvent;
use EscolaLms\CourseAccess\Models\Course;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\CourseAccess\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

class CourseAccessEnquiryCreateApiTest extends TestCase
{
    use CreatesUsers;

    private $course;
    private $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseAccessPermissionSeeder::class);
        $this->course = Course::factory()->create();
        $this->student = $this->makeStudent();
    }

    public function testCourseAccessEnquiryCreateUnauthorized(): void
    {
        $this->postJson('api/course-access-enquiries', [
            'course_id' => $this->course->getKey(),
        ])->assertUnauthorized();
    }

    public function testCourseAccessEnquiryCreate(): void
    {
        Event::fake([CourseAccessEnquiryStudentCreatedEvent::class]);

        $data = [
            'field1' => 'value_1',
            'field2' => 'value_2',
            'field3' => [
                'field3_1' => 'value_3'
            ],
        ];

        $this->actingAs($this->student, 'api')
            ->postJson('api/course-access-enquiries', [
                'course_id' => $this->course->getKey(),
                'data' => $data,
            ])
            ->assertCreated()
            ->assertJsonFragment(['data' => $data]);

        $this->assertDatabaseHas('course_access_enquiries', [
            'course_id' => $this->course->getKey(),
            'user_id' => $this->student->getKey(),
            'status' => EnquiryStatusEnum::PENDING,
        ]);

        Event::fake([CourseAccessEnquiryStudentCreatedEvent::class]);
    }

    public function testCourseAccessEnquiryCreateAutoApprove(): void
    {
        Config::set(EscolaLmsCourseAccessServiceProvider::CONFIG_KEY . '.auto_accept_access_request', SettingStatusEnum::ENABLED);
        Event::fake([CourseAccessEnquiryStudentCreatedEvent::class]);

        $data = [
            'field1' => 'value_1',
            'field2' => 'value_2',
            'field3' => [
                'field3_1' => 'value_3'
            ],
        ];

        $this->actingAs($this->student, 'api')
            ->postJson('api/course-access-enquiries', [
                'course_id' => $this->course->getKey(),
                'data' => $data,
            ])
            ->assertCreated()
            ->assertJsonFragment(['data' => $data]);

        $this->assertDatabaseHas('course_access_enquiries', [
            'course_id' => $this->course->getKey(),
            'user_id' => $this->student->getKey(),
            'status' => EnquiryStatusEnum::APPROVED,
        ]);

        Event::fake([CourseAccessEnquiryStudentCreatedEvent::class]);
    }

    public function testShouldThrowErrorWhenCourseAccessEnquiryAlreadyExists(): void
    {
        CourseAccessEnquiry::factory()
            ->state([
                'course_id' => $this->course->getKey(),
                'user_id' => $this->student->getKey(),
            ])
            ->create();

        $this->actingAs($this->student, 'api')->postJson('api/course-access-enquiries', [
            'course_id' => $this->course->getKey(),
        ])->assertStatus(400);
    }

    public function testShouldDispatchEventToAdminAfterCreatingCourseAccessEnquiry(): void
    {
        Event::fake([CourseAccessEnquiryAdminCreatedEvent::class]);

        $this->makeAdmin();

        $this->actingAs($this->student, 'api')->postJson('api/course-access-enquiries', [
            'course_id' => $this->course->getKey(),
        ])->assertCreated();

        Event::assertDispatched(function (CourseAccessEnquiryAdminCreatedEvent $event) {
            $this->assertTrue($event->getUser()->hasPermissionTo(CourseAccessPermissionEnum::APPROVE_COURSE_ACCESS_ENQUIRY));
            $this->assertEquals($this->course->getKey(), $event->getCourseAccessEnquiry()->course_id);
            return true;
        });
    }
}
