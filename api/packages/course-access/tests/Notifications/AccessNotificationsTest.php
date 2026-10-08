<?php

namespace EscolaLms\CourseAccess\Tests\Notifications;

use EscolaLms\Core\Models\User as ModelsUser;
use EscolaLms\CourseAccess\Tests\TestCase;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Events\CourseAccessStarted;
use EscolaLms\Courses\Events\CourseAssigned;
use EscolaLms\Courses\Events\CourseUnassigned;
use EscolaLms\CourseAccess\Models\Course;
use EscolaLms\Courses\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class AccessNotificationsTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    public function testUserAssignedToCourseNotification(): void
    {
        Notification::fake();
        Event::fake([CourseAccessStarted::class, CourseAssigned::class]);

        $course = Course::factory()->create([
            'author_id' => $this->user->id,
            'status' => CourseStatusEnum::PUBLISHED,
        ]);

        $student = User::factory()->create();

        $this->response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/courses/' . $course->id . '/access/add/', [
                'users' => [$student->getKey()],
            ]);

        $this->response->assertOk();
        Event::assertDispatched(CourseAccessStarted::class);

        $user = ModelsUser::find($student->getKey());
        Event::assertDispatched(CourseAssigned::class, function (CourseAssigned $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });
    }

    public function testUserUnassignedFromCourseNotification()
    {
        Notification::fake();
        Event::fake(CourseUnassigned::class);

        $course = Course::factory()->create([
            'author_id' => $this->user->id,
            'status' => CourseStatusEnum::PUBLISHED
        ]);
        $student = User::factory()->create();
        $student->courses()->save($course);

        $this->response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/courses/' . $course->id . '/access/remove/', [
                'users' => [$student->getKey()],
            ]);

        $this->response->assertOk();

        $user = ModelsUser::find($student->getKey());
        Event::assertDispatched(CourseUnassigned::class, function (CourseUnassigned $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });
    }
}
