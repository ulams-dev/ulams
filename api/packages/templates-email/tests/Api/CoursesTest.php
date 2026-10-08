<?php

namespace EscolaLms\TemplatesEmail\Tests\Api;

use EscolaLms\Core\Models\User as CoreUser;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Events\CourseAssigned;
use EscolaLms\Courses\Events\CourseDeadlineSoon;
use EscolaLms\Courses\Events\CourseFinished;
use EscolaLms\Courses\Events\CourseUnassigned;
use EscolaLms\Courses\Jobs\CheckForDeadlines;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Models\User;
use EscolaLms\Courses\Tests\ProgressConfigurable;
use EscolaLms\Courses\ValueObjects\CourseProgressCollection;
use EscolaLms\Templates\Listeners\TemplateEventListener;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class CoursesTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;
    use ProgressConfigurable;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class)) {
            $this->markTestSkipped('Courses package not installed');
        }
        if (!class_exists(\EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider::class)) {
            $this->markTestSkipped('Course-Access package not installed');
        }
        if (!class_exists(\EscolaLms\Scorm\EscolaLmsScormServiceProvider::class)) {
            $this->markTestSkipped('Scorm package not installed');
        }
    }

    public function testDeadlineNotification()
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'active_to' => Carbon::now()->addDays(config('escolalms_courses.reminder_of_deadline_count_days'))]);
        $lesson = Lesson::factory()->create([
            'course_id' => $course->getKey()
        ]);
        $topics = Topic::factory(2)->create([
            'lesson_id' => $lesson->getKey(),
            'active' => true,
        ]);
        $user->courses()->save($course);
        $progress = CourseProgressCollection::make($user, $course);

        $checkForDealines = new CheckForDeadlines();
        $checkForDealines->handle();

        Event::assertDispatched(CourseDeadlineSoon::class, function (CourseDeadlineSoon $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new CourseDeadlineSoon($user, $course));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user, $course) {
            $this->assertEquals(__('Deadline for course ":course"', ['course' => $course->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            return true;
        });
    }

    public function testUserAssignedToCourseNotification()
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        $admin = $this->makeAdmin();

        $course = Course::factory()->create([
            'author_id' => $admin->id,
            'status' => CourseStatusEnum::PUBLISHED
        ]);

        $student = User::factory()->create();

        $this->response = $this->actingAs($admin, 'api')->post('/api/admin/courses/' . $course->id . '/access/add/', [
            'users' => [$student->getKey()]
        ]);

        $this->response->assertOk();

        $user = CoreUser::find($student->getKey());
        Event::assertDispatched(CourseAssigned::class, function (CourseAssigned $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new CourseAssigned($user, $course));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user, $course) {
            $this->assertEquals(__('You have been assigned to ":course"', ['course' => $course->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            return true;
        });
    }

    public function testUserUnassignedFromCourseNotification()
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        $admin = $this->makeAdmin();

        $course = Course::factory()->create([
            'author_id' => $admin->id,
            'status' => CourseStatusEnum::PUBLISHED
        ]);
        $student = User::factory()->create();
        $student->courses()->save($course);

        $this->response = $this->actingAs($admin, 'api')->post('/api/admin/courses/' . $course->id . '/access/remove/', [
            'users' => [$student->getKey()]
        ]);

        $this->response->assertOk();

        $user = CoreUser::find($student->getKey());
        Event::assertDispatched(CourseUnassigned::class, function (CourseUnassigned $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new CourseUnassigned($user, $course));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user, $course) {
            $this->assertEquals(__('You have been unassigned from ":course"', ['course' => $course->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            return true;
        });
    }

    public function testUserFinishedCourseNotification()
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $lesson = Lesson::factory([
            'course_id' => $course->getKey()
        ])->create();
        $topics = Topic::factory(2)->create([
            'lesson_id' => $lesson->getKey(),
            'active' => true,
        ]);

        $student = User::factory([
            'points' => 0,
        ])->create();

        $courseProgress = CourseProgressCollection::make($student, $course);
        $this->assertFalse($courseProgress->isFinished());

        $this->response = $this->actingAs($student, 'api')->json(
            'PATCH',
            '/api/courses/progress/' . $course->getKey(),
            ['progress' => $this->getProgressUpdate($course)]
        );
        $courseProgress = CourseProgressCollection::make($student, $course);
        $this->response->assertOk();
        $this->assertTrue($courseProgress->isFinished());

        $user = CoreUser::find($student->getKey());

        Event::assertDispatched(CourseFinished::class, function (CourseFinished $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new CourseFinished($user, $course));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user, $course) {
            $this->assertEquals(__('You finished ":course"', ['course' => $course->title]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            return true;
        });

        if (!Event::hasDispatched(CourseFinished::class)) {
            $this->markTestIncomplete(
                'CourseFinished is not dispatched in Courses'
            );
        }
    }
}
