<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use Ulams\CourseAccess\Events\CourseAccessEnquiryAdminCreatedEvent;
use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Ulams\Courses\Models\Course;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class CourseAccessEnquiryTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\Ulams\CourseAccess\UlamsCourseAccessServiceProvider::class)) {
            $this->markTestSkipped('Course-Access package not installed');
        }
        
        $this->seed(CourseAccessPermissionSeeder::class);
    }

    public function testAdminNotificationOnCourseEnquiryCreated(): void
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $course = Course::factory()->create();

        $this->actingAs($student, 'api')
            ->postJson('api/course-access-enquiries', [
                'course_id' => $course->getKey(),
            ])
            ->assertCreated();

        $enquiry = CourseAccessEnquiry::latest()->first();

        Event::assertDispatched(function (CourseAccessEnquiryAdminCreatedEvent $event) use ($enquiry) {
            $this->assertEquals($event->courseAccessEnquiry->getKey(), $enquiry->getKey());
            return true;
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new CourseAccessEnquiryAdminCreatedEvent($admin, $enquiry));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($admin, $enquiry) {
            $this->assertEquals(__('New course access enquiry'), $mailable->subject);
            $this->assertTrue($mailable->hasTo($admin->email));
            return true;
        });
    }
}
