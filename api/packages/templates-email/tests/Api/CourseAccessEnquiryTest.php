<?php

namespace EscolaLms\TemplatesEmail\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use EscolaLms\CourseAccess\Events\CourseAccessEnquiryAdminCreatedEvent;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Templates\Listeners\TemplateEventListener;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
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

        if (!class_exists(\EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider::class)) {
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
