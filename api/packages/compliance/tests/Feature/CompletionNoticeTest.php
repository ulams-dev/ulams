<?php

namespace Ulams\Compliance\Tests\Feature;

use Ulams\Compliance\Mail\TrainingAssignedVariables;
use Ulams\Compliance\Mail\TrainingCompletedForManagerVariables;
use Ulams\Compliance\Mail\TrainingCompletedVariables;
use Ulams\Compliance\Models\ManagerLink;
use Ulams\Compliance\Services\AssignmentService;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\Compliance\Tests\NotificationsTestCase;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\TemplatesPdf\Models\FabricPDF;

/** The notice a required course sends when it is completed, and to whom. */
class CompletionNoticeTest extends NotificationsTestCase
{
    use CreatesUsers;

    private function course(bool $mandatory = true): Course
    {
        $course = Course::factory()->create(['title' => 'Safety', 'language' => 'en', 'status' => CourseStatusEnum::PUBLISHED]);
        app(CourseSettings::class)->save($course->getKey(), ['mandatory' => $mandatory]);

        return $course;
    }

    private function learner(string $email = 'ada@example.test'): CoreUser
    {
        return CoreUser::find($this->makeStudent(['email' => $email, 'first_name' => 'Ada', 'last_name' => 'Lovelace'])->getKey());
    }

    private function managerOf(CoreUser $learner): CoreUser
    {
        $manager = CoreUser::find($this->makeStudent(['email' => 'grace@example.test', 'first_name' => 'Grace', 'last_name' => 'Hopper'])->getKey());
        ManagerLink::query()->create(['user_id' => $learner->getKey(), 'manager_id' => $manager->getKey()]);

        return $manager;
    }

    private function finish(CoreUser $learner, Course $course): void
    {
        CourseUserPivot::query()->where('user_id', $learner->getKey())->where('course_id', $course->getKey())->update(['finished' => true]);
        event(new CourseFinished($learner, $course));
    }

    public function testTheLearnerAndTheManagerAreToldOncePerAssignment(): void
    {
        $course = $this->course();
        $learner = $this->learner();
        $this->managerOf($learner);
        app(AssignmentService::class)->assign($learner, $course);

        $this->finish($learner, $course);
        $this->finish($learner, $course);

        $this->assertSame(['TrainingAssigned' => 1, 'TrainingCompleted' => 1], $this->inApp($learner->getKey()));
        $subjects = array_column(array_filter($this->sentMails(), fn (array $m) => str_starts_with($m[1], 'Training completed')), 1);
        $this->assertSame(['Training completed: Safety', 'Training completed by Ada Lovelace: Safety'], array_values($subjects));
        $this->assertStringContainsString('Ada Lovelace has completed the required course "Safety".', $this->mailBody('grace@example.test', 'Training completed by Ada Lovelace: Safety'));
    }

    public function testTheManagerIsOnlyToldAboutMandatoryCourses(): void
    {
        $course = $this->course(mandatory: false);
        $learner = $this->learner();
        $manager = $this->managerOf($learner);
        app(AssignmentService::class)->assign($learner, $course);

        $this->finish($learner, $course);

        $this->assertSame(['TrainingAssigned' => 1, 'TrainingCompleted' => 1], $this->inApp($learner->getKey()));
        $this->assertSame([], $this->inApp($manager->getKey()));
    }

    public function testNoManagerLinkMeansNoManagerNotice(): void
    {
        $course = $this->course();
        $learner = $this->learner();
        app(AssignmentService::class)->assign($learner, $course);

        $this->finish($learner, $course);

        $this->assertSame(['TrainingAssigned' => 1, 'TrainingCompleted' => 1], $this->inApp($learner->getKey()));
        $this->assertSame(1, count(array_filter($this->sentMails(), fn (array $m) => str_starts_with($m[1], 'Training completed'))));
    }

    public function testACourseAlreadyCoveredByAValidCertificateSendsNoCompletionNotice(): void
    {
        $course = $this->course();
        $learner = $this->learner();
        $this->managerOf($learner);
        FabricPDF::factory()->createOne(['user_id' => $learner->getKey(), 'assignable_type' => Course::class, 'assignable_id' => $course->getKey(), 'expires_at' => now()->addYear()]);

        $assignment = app(AssignmentService::class)->assign($learner, $course);

        $this->assertSame('completed', $assignment->status);
        $this->assertSame([], $this->inApp($learner->getKey()));
        $this->assertSame([], array_filter($this->sentMails(), fn (array $m) => str_starts_with($m[1], 'Training')));
    }

    public function testTheCertificateLinkPointsAtTheVerificationPageOnceTheAssignmentHasOne(): void
    {
        $course = $this->course();
        $learner = $this->learner();
        $assignment = app(AssignmentService::class)->assign($learner, $course);
        $assignment->forceFill(['certificate_id' => 41])->save();

        $event = new \Ulams\Compliance\Events\TrainingCompleted($learner, $course, $assignment);
        $variables = TrainingCompletedVariables::variablesFromEvent(new \Ulams\Templates\Events\EventWrapper($event));

        $this->assertSame('https://academy.example.test/certificates/verify/41', $variables[TrainingCompletedVariables::VAR_CERTIFICATE_URL]);
        $this->assertSame('', $variables[TrainingCompletedVariables::VAR_PATH_CONTEXT]);
    }

    public function testTheTemplatePreviewKnowsEveryNewVariable(): void
    {
        $assigned = TrainingAssignedVariables::mockedVariables();
        $completed = TrainingCompletedVariables::mockedVariables();

        $this->assertSame('Step 2 of 4 of the learning path "Onboarding" is now open.', $assigned['@VarPathContext']);
        $this->assertSame('This course is step 2 of 4 of the learning path "Onboarding".', $completed['@VarPathContext']);
        foreach ([$assigned, $completed, TrainingCompletedForManagerVariables::mockedVariables()] as $mocked) {
            $this->assertSame(['2', '4', 'Onboarding', 'Data protection'], [$mocked['@VarStepNumber'], $mocked['@VarStepCount'], $mocked['@VarPathTitle'], $mocked['@VarNextStep']]);
        }
        $this->assertSame('https://academy.example.test/account/training', $completed['@VarCertificateUrl']);
        $this->assertSame('Grace Hopper', TrainingCompletedForManagerVariables::mockedVariables()['@VarLearnerName']);
    }
}
