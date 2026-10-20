<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\Notification;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Services\AssignmentService;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Models\User;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Models\FabricPDF;

class CompletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(TemplatesPdfSeeder::class);
    }

    private function finish(User $student, Course $course): void
    {
        CourseUserPivot::query()->where('user_id', $student->getKey())->where('course_id', $course->getKey())->update(['finished' => true]);
        event(new CourseFinished(CoreUser::find($student->getKey()), $course));
    }

    public function testFinishingTheCourseClosesTheAssignmentAndLinksTheCertificate(): void
    {
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $student = User::factory()->create();
        $assignment = app(AssignmentService::class)->assign(CoreUser::find($student->getKey()), $course, now()->addWeek());

        $this->finish($student, $course);

        $assignment->refresh();
        $this->assertSame('completed', $assignment->status);
        $this->assertNotNull($assignment->completed_at);
        $certificate = FabricPDF::where('user_id', $student->getKey())->firstOrFail();
        $this->assertSame($certificate->getKey(), $assignment->certificate_id);
    }

    public function testRemovingAccessDoesNotCountAsCompletion(): void
    {
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $student = User::factory()->create();
        $assignment = app(AssignmentService::class)->assign(CoreUser::find($student->getKey()), $course, now()->addWeek());

        // access removal fires CourseFinished too, but the course was not finished
        event(new CourseFinished(CoreUser::find($student->getKey()), $course));

        $this->assertSame('assigned', $assignment->fresh()->status);
    }

    public function testANewCertificateSupersedesTheOldOneForTheSameCourse(): void
    {
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $student = User::factory()->create();
        $listener = app(TemplateEventListener::class);

        $listener->handle(new CourseFinished(CoreUser::find($student->getKey()), $course));
        $listener->handle(new CourseFinished(CoreUser::find($student->getKey()), $course));

        [$old, $new] = FabricPDF::where('user_id', $student->getKey())->orderBy('id')->get()->all();
        $this->assertSame($new->getKey(), $old->fresh()->superseded_by_id);
        $this->assertNull($new->fresh()->superseded_by_id);
    }

    public function testAssignmentWithoutAnOpenOneUsesTheDefaultDueDays(): void
    {
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        app(\Ulams\Compliance\Services\CourseSettings::class)->save($course->getKey(), ['mandatory' => true, 'default_due_days' => 30]);
        $student = User::factory()->create();

        $assignment = app(AssignmentService::class)->assign(CoreUser::find($student->getKey()), $course);

        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $assignment->due_at->timestamp, 5);
        $this->assertSame(1, Assignment::query()->count());
    }
}
