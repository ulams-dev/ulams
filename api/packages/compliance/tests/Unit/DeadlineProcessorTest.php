<?php

namespace Ulams\Compliance\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Ulams\Compliance\Enums\AssignmentSource;
use Ulams\Compliance\Events\CertificateExpired;
use Ulams\Compliance\Events\CertificateExpiringSoon;
use Ulams\Compliance\Events\TrainingAssigned;
use Ulams\Compliance\Events\TrainingDueSoon;
use Ulams\Compliance\Events\TrainingEscalated;
use Ulams\Compliance\Events\TrainingOverdue;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Models\ManagerLink;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\Compliance\Services\DeadlineProcessor;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\TemplatesPdf\Models\FabricPDF;

class DeadlineProcessorTest extends TestCase
{
    use CreatesUsers;

    private function course(array $settings = []): Course
    {
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        app(CourseSettings::class)->save($course->getKey(), $settings + ['mandatory' => true]);

        return $course;
    }

    private function certificate(User $user, Course $course, $expiresAt, array $extra = []): FabricPDF
    {
        return FabricPDF::factory()->createOne($extra + [
            'user_id' => $user->getKey(), 'assignable_type' => Course::class, 'assignable_id' => $course->getKey(), 'expires_at' => $expiresAt,
        ]);
    }

    private function run_(): array
    {
        return app(DeadlineProcessor::class)->process(now());
    }

    public function testCertificateReminderIsSentOncePerReachedOffsetAndOnlyTheMostUrgent(): void
    {
        Event::fake([CertificateExpiringSoon::class, TrainingAssigned::class]);
        $course = $this->course(['reminder_days' => [30, 7, 1], 'recertify_before_days' => 0]);
        $user = $this->makeStudent();
        $cert = $this->certificate($user, $course, now()->addDays(20));

        $this->run_();
        $this->run_();
        Event::assertDispatchedTimes(CertificateExpiringSoon::class, 1); // the 30-day offset, once

        $this->travel(14)->days(); // 6 days left: the 7-day offset
        $this->run_();
        Event::assertDispatchedTimes(CertificateExpiringSoon::class, 2);
        Event::assertDispatched(CertificateExpiringSoon::class, fn ($e) => $e->daysLeft === 6 && $e->certificate->is($cert) && $e->getUser()->is($user));

        $this->travel(10)->days(); // expired
        $this->run_();
        Event::assertDispatchedTimes(CertificateExpiringSoon::class, 2);
    }

    public function testExpiredCertificatesAreAnnouncedOnceAndRevokedOrReplacedOnesNever(): void
    {
        Event::fake([CertificateExpired::class, TrainingAssigned::class]);
        $course = $this->course(['recertify_before_days' => 0]);
        [$a, $b, $c, $d] = [$this->makeStudent(), $this->makeStudent(), $this->makeStudent(), $this->makeStudent()];
        $this->certificate($a, $course, now()->subDay());
        $this->certificate($b, $course, now()->subDay(), ['revoked_at' => now()]);
        $this->certificate($c, $course, now()->subDay(), ['superseded_by_id' => 999]);
        $this->certificate($d, $course, now()->subDays(40)); // too old to announce

        $this->run_();
        $this->run_();

        Event::assertDispatchedTimes(CertificateExpired::class, 1);
        Event::assertDispatched(CertificateExpired::class, fn ($e) => $e->getUser()->is($a));
    }

    public function testRecertificationOpensAnAssignmentDueOnTheExpiryAndStartsTheCourseOver(): void
    {
        Event::fake([TrainingAssigned::class, CertificateExpiringSoon::class]);
        $course = $this->course(['recertify_before_days' => 30]);
        $user = $this->makeStudent();
        $user->courses()->attach($course->getKey(), ['finished' => true]);
        $cert = $this->certificate($user, $course, now()->addDays(20));
        $outside = $this->makeStudent();
        $this->certificate($outside, $course, now()->addDays(90));

        $result = $this->run_();
        $this->run_();

        $this->assertSame(1, $result['recertifications']);
        $assignment = Assignment::query()->where('user_id', $user->getKey())->firstOrFail();
        $this->assertSame(AssignmentSource::RECERTIFICATION, $assignment->source);
        $this->assertSame($cert->getKey(), $assignment->recertifies_certificate_id);
        $this->assertSame($cert->expires_at->timestamp, $assignment->due_at->timestamp);
        $this->assertSame(1, Assignment::query()->count());
        $this->assertFalse((bool) CourseUserPivot::query()->where('user_id', $user->getKey())->first()->finished);
        Event::assertDispatchedTimes(TrainingAssigned::class, 1);
        // the old certificate stays valid until it expires
        $this->assertSame('valid', $cert->fresh()->status());
    }

    public function testCoursesWithoutSettingsAreNotRecertified(): void
    {
        Event::fake([TrainingAssigned::class]);
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $this->certificate($this->makeStudent(), $course, now()->addDays(5));

        $this->run_();

        $this->assertSame(0, Assignment::query()->count());
    }

    public function testRecertificationClearsTheLearnersProgress(): void
    {
        Event::fake([TrainingAssigned::class]);
        $course = $this->course(['recertify_before_days' => 30]);
        $topic = \Ulams\Courses\Models\Topic::factory()->create(['lesson_id' => \Ulams\Courses\Models\Lesson::factory()->create(['course_id' => $course->getKey()])->getKey()]);
        $user = $this->makeStudent();
        $other = $this->makeStudent();
        $user->courses()->attach($course->getKey(), ['finished' => true]);
        foreach ([$user, $other] as $u) {
            CourseProgress::query()->create(['user_id' => $u->getKey(), 'topic_id' => $topic->getKey(), 'status' => 2, 'seconds' => 10]);
        }
        $this->certificate($user, $course, now()->addDays(3));

        $this->run_();

        $this->assertSame(0, CourseProgress::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(1, CourseProgress::query()->where('user_id', $other->getKey())->count());
    }

    public function testDueSoonOverdueAndEscalationToTheManager(): void
    {
        Event::fake([TrainingAssigned::class, TrainingDueSoon::class, TrainingOverdue::class, TrainingEscalated::class]);
        $course = $this->course(['reminder_days' => [7, 1], 'escalation_days' => [1, 7]]);
        [$learner, $manager] = [$this->makeStudent(), $this->makeStudent()];
        $assignment = app(\Ulams\Compliance\Services\AssignmentService::class)->assign($learner, $course, now()->addDays(5));

        $this->run_();
        Event::assertDispatchedTimes(TrainingDueSoon::class, 1);
        Event::assertNotDispatched(TrainingOverdue::class);

        $this->travel(6)->days(); // one day overdue
        $this->run_();
        Event::assertDispatchedTimes(TrainingOverdue::class, 1);
        Event::assertNotDispatched(TrainingEscalated::class); // nobody to escalate to yet

        ManagerLink::query()->create(['user_id' => $learner->getKey(), 'manager_id' => $manager->getKey()]);
        $this->run_();
        $this->run_();
        Event::assertDispatchedTimes(TrainingEscalated::class, 1);
        Event::assertDispatched(TrainingEscalated::class, fn ($e) => $e->getUser()->is($manager) && $e->learner->is($learner) && $e->assignment->is($assignment));
        $this->assertSame(1, $assignment->fresh()->escalation_level);

        $this->travel(7)->days(); // the 7-day level
        $this->run_();
        Event::assertDispatchedTimes(TrainingEscalated::class, 2);
        $this->assertSame(2, $assignment->fresh()->escalation_level);
        Event::assertDispatchedTimes(TrainingOverdue::class, 1);
    }

    public function testCompletedAssignmentsAreNeverChased(): void
    {
        Event::fake([TrainingAssigned::class, TrainingDueSoon::class, TrainingOverdue::class, TrainingEscalated::class]);
        $course = $this->course();
        $learner = $this->makeStudent();
        $assignment = app(\Ulams\Compliance\Services\AssignmentService::class)->assign($learner, $course, now()->subDay());
        $assignment->forceFill(['status' => 'completed'])->save();

        $this->run_();

        Event::assertNotDispatched(TrainingOverdue::class);
        Event::assertNotDispatched(TrainingEscalated::class);
    }

    public function testTheCommandPrintsTheCounts(): void
    {
        $this->artisan('compliance:process')->assertSuccessful()->expectsOutputToContain('escalations');
    }
}
