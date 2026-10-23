<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\Notification;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Services\AssignmentService;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\Compliance\Services\FamilyCourses;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Models\User;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\CourseVariants\UlamsCourseVariantsServiceProvider;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Mandatory training on a course with variants (ADR 0192, Q3): finishing any variant fulfils it unless an admin pinned one,
 * and a variant course uses the compliance settings of the family's default course.
 */
class FamilyObligationsTest extends TestCase
{
    private Course $base;
    private Course $expert;
    private VariantGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(TemplatesPdfSeeder::class);
        $this->app->register(UlamsCourseVariantsServiceProvider::class);
        $this->base = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'Safety']);
        $this->expert = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'Safety (Expert)']);
        $service = $this->app->make(CourseVariantServiceContract::class);
        $service->addMember($this->base->getKey(), $this->expert->getKey(), 'expert', 'Expert', 'expert', null, 1);
        $this->group = VariantGroup::query()->where('default_course_id', $this->base->getKey())->firstOrFail();
        $this->app->make(FamilyCourses::class)->forget();
    }

    private function learner(): CoreUser
    {
        return CoreUser::find(User::factory()->create()->getKey());
    }

    private function finish(CoreUser $student, Course $course): void
    {
        CourseUserPivot::query()->updateOrCreate(['user_id' => $student->getKey(), 'course_id' => $course->getKey()], ['finished' => true]);
        event(new CourseFinished($student, $course));
    }

    public function testFinishingAVariantFulfilsTheObligationOnTheFamily(): void
    {
        $student = $this->learner();
        $assignment = $this->app->make(AssignmentService::class)->assign($student, $this->base, now()->addWeek());
        $this->assertSame($this->base->getKey(), $assignment->course_id);

        $this->finish($student, $this->expert);

        $assignment->refresh();
        $this->assertSame('completed', $assignment->status);
        $certificate = FabricPDF::query()->where('user_id', $student->getKey())->firstOrFail();
        $this->assertSame($this->expert->getKey(), $certificate->assignable_id, 'the certificate is the variant\'s');
        $this->assertSame($certificate->getKey(), $assignment->certificate_id);
    }

    public function testAPinnedVariantIsTheOnlyOneThatCountsAndTheLearnerIsGivenIt(): void
    {
        $this->group->forceFill(['pinned_course_id' => $this->expert->getKey()])->save();
        $this->app->make(FamilyCourses::class)->forget();
        $student = $this->learner();

        $assignment = $this->app->make(AssignmentService::class)->assign($student, $this->base, now()->addWeek());

        $this->assertSame($this->expert->getKey(), $assignment->course_id, 'the obligation is on the pinned course');
        $this->assertTrue(CourseUserPivot::query()->where(['user_id' => $student->getKey(), 'course_id' => $this->expert->getKey()])->exists());

        $this->finish($student, $this->base);
        $this->assertSame('assigned', $assignment->fresh()->status, 'the other variant does not count');
        $this->finish($student, $this->expert);
        $this->assertSame('completed', $assignment->fresh()->status);
    }

    public function testAValidCertificateOfAnyVariantSatisfiesTheObligationAtOnce(): void
    {
        $student = $this->learner();
        $this->finish($student, $this->expert); // certificate of the variant, issued before anything was assigned
        $this->assertSame(1, FabricPDF::query()->where('user_id', $student->getKey())->count());

        $assignment = $this->app->make(AssignmentService::class)->assign($student, $this->base, now()->addWeek());

        $this->assertSame('completed', $assignment->status);
        $this->assertNotNull($assignment->certificate_id);
    }

    public function testAVariantUsesTheSettingsOfTheDefaultCourse(): void
    {
        $settings = $this->app->make(CourseSettings::class);
        $settings->save($this->base->getKey(), ['mandatory' => true, 'validity_days' => 365]);

        $this->assertTrue($settings->for($this->expert->getKey())->mandatory);
        $this->assertSame(365, $settings->for($this->expert->getKey())->validity_days);
        $this->assertFalse($settings->has($this->expert->getKey()), 'inherited, not stored');

        $settings->save($this->expert->getKey(), ['mandatory' => false]);
        $this->assertFalse($settings->for($this->expert->getKey())->mandatory, 'a row of its own wins');
    }

    public function testCoursesOutsideAFamilyAreUnchanged(): void
    {
        $solo = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $families = $this->app->make(FamilyCourses::class);

        $this->assertSame([$solo->getKey()], $families->counting($solo->getKey()));
        $this->assertSame([$solo->getKey()], $families->fulfilledBy($solo->getKey()));
        $this->assertSame($solo->getKey(), $families->assignedCourse($solo->getKey()));
        $this->assertSame($solo->getKey(), $families->policyCourse($solo->getKey()));
        $this->assertSame(0, Assignment::query()->count());
    }
}
