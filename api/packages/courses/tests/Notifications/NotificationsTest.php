<?php

namespace Ulams\Courses\Tests\Notifications;

use Ulams\Core\Models\User as ModelsUser;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseAccessStarted;
use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Events\CourseAccessFinished;
use Ulams\Courses\Events\CourseUnassigned;
use Ulams\Courses\Events\CourseDeadlineSoon;
use Ulams\Courses\Jobs\CheckForDeadlines;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Models\User;
use Ulams\Courses\Tests\ProgressConfigurable;
use Ulams\Courses\Tests\TestCase;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class NotificationsTest extends TestCase
{
    use DatabaseTransactions;
    use ProgressConfigurable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    public function testDeadlineNotification()
    {
        Notification::fake();
        Event::fake();

        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'active_to' => Carbon::now()->addDays(config('ulams_courses.reminder_of_deadline_count_days'))]);
        $lesson = Lesson::factory()->create([
            'course_id' => $course->getKey()
        ]);
        Topic::factory(2)->create([
            'lesson_id' => $lesson->getKey(),
            'active' => true,
        ]);
        $user->courses()->save($course);
        CourseProgressCollection::make($user, $course);

        $checkForDealines = new CheckForDeadlines();
        $checkForDealines->handle();

        Event::assertDispatched(CourseDeadlineSoon::class, function (CourseDeadlineSoon $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });
    }

    public function testDeadlineNotificationNotDispach()
    {
        Notification::fake();
        Event::fake();

        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'active_to' => Carbon::now()->addHour()]);
        $lesson = Lesson::factory()->create([
            'course_id' => $course->getKey()
        ]);
        Topic::factory(2)->create([
            'lesson_id' => $lesson->getKey(),
            'active' => true,
        ]);

        $user->courses()->save($course);
        CourseProgressCollection::make($user, $course);
        $checkForDealines = new CheckForDeadlines();
        $checkForDealines->handle();

        Event::assertNotDispatched(CourseDeadlineSoon::class, function (CourseDeadlineSoon $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });
    }

    public function testUserFinishedCourseNotification()
    {
        Notification::fake();
        Event::fake();

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

        Event::assertDispatched(CourseAccessFinished::class);

        $user = ModelsUser::find($student->getKey());
        Event::assertDispatched(CourseAccessFinished::class, function (CourseAccessFinished $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });
    }
}
