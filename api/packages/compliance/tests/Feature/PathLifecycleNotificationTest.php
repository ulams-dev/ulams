<?php

namespace Ulams\Compliance\Tests\Feature;

use Carbon\Carbon;
use Ulams\Compliance\Models\ManagerLink;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\Compliance\Services\DeadlineProcessor;
use Ulams\Compliance\Services\PathEnrollmentService;
use Ulams\Compliance\Services\PathService;
use Ulams\Compliance\Tests\NotificationsTestCase;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;

/**
 * One learner walks a three-step path (A, then B, then C) with a frozen clock. Every compliance notice, e-mail and
 * in-app, is sent exactly once, in order, and a rerun of the daily job or of the events sends nothing more.
 */
class PathLifecycleNotificationTest extends NotificationsTestCase
{
    use CreatesUsers;

    private const SUBJECTS = '/^(Learning path|Required training|Training due soon|Overdue training|Training completed)/';

    private int $seen = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-02 09:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return list<array{0: string, 1: string}> */
    private function complianceMails(): array
    {
        return array_values(array_filter($this->sentMails(), fn (array $mail) => preg_match(self::SUBJECTS, $mail[1]) === 1));
    }

    /** The compliance mails sent since the last call. */
    private function newMails(): array
    {
        $all = $this->complianceMails();
        $new = array_slice($all, $this->seen);
        $this->seen = count($all);

        return $new;
    }

    private function day(int $days, string $time = '09:00:00'): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-02 ' . $time, 'UTC')->addDays($days));
    }

    private function process(): void
    {
        app(DeadlineProcessor::class)->process(now());
    }

    private function finish(CoreUser $learner, Course $course): void
    {
        CourseUserPivot::query()->where('user_id', $learner->getKey())->where('course_id', $course->getKey())->update(['finished' => true]);
        event(new CourseFinished($learner, $course));
    }

    public function testALearnerWalksAThreeStepPathAndGetsEveryNoticeOnce(): void
    {
        [$a, $b, $c] = collect(['Course A', 'Course B', 'Course C'])->map(function (string $title) {
            $course = Course::factory()->create(['title' => $title, 'language' => 'en', 'status' => CourseStatusEnum::PUBLISHED]);
            app(CourseSettings::class)->save($course->getKey(), ['mandatory' => true, 'default_due_days' => $title === 'Course A' ? 10 : 30, 'reminder_days' => [3], 'escalation_days' => [2]]);

            return $course;
        })->all();
        $learner = CoreUser::find($this->makeStudent(['email' => 'ada@example.test', 'first_name' => 'Ada', 'last_name' => 'Lovelace'])->getKey());
        $manager = CoreUser::find($this->makeStudent(['email' => 'grace@example.test', 'first_name' => 'Grace', 'last_name' => 'Hopper'])->getKey());
        ManagerLink::query()->create(['user_id' => $learner->getKey(), 'manager_id' => $manager->getKey()]);
        $path = app(PathService::class)->save(['title' => 'Onboarding', 'steps' => [
            ['course_id' => $a->getKey()],
            ['course_id' => $b->getKey(), 'prerequisites' => [$a->getKey()]],
            ['course_id' => $c->getKey(), 'prerequisites' => [$b->getKey()]],
        ]]);
        $ada = 'ada@example.test';
        $grace = 'grace@example.test';

        // day 0: enrolled, the first step is assigned (only the startable course)
        app(PathEnrollmentService::class)->enroll($learner, $path);
        app(PathEnrollmentService::class)->enroll($learner, $path); // enrolling again is a no-op
        $this->assertSame([[$ada, 'Learning path: Onboarding'], [$ada, 'Required training: Course A']], $this->newMails());
        $this->assertStringContainsString('Step 1 of 3 of the learning path &quot;Onboarding&quot; is now open.', $this->mailBody($ada, 'Required training: Course A'));

        // day 8: A is due in two days, the 3-day reminder goes out once
        $this->day(8);
        $this->process();
        $this->process();
        $this->assertSame([[$ada, 'Training due soon: Course A']], $this->newMails());

        // day 11: A is a day overdue; the first escalation level (2 days) is not reached yet
        $this->day(11);
        $this->process();
        $this->process();
        $this->assertSame([[$ada, 'Overdue training: Course A']], $this->newMails());
        $this->assertStringContainsString('This course is step 1 of 3 of the learning path &quot;Onboarding&quot;.', $this->mailBody($ada, 'Overdue training: Course A'));

        // day 12: two days overdue, the manager is told once
        $this->day(12);
        $this->process();
        $this->process();
        $this->assertSame([[$grace, 'Overdue training of Ada Lovelace: Course A']], $this->newMails());

        // day 13: A is finished; the learner and the manager hear about it and step 2 opens
        $this->day(13);
        $this->finish($learner, $a);
        $this->finish($learner, $a); // a second CourseFinished for the same course closes nothing new
        $this->assertSame([
            [$ada, 'Training completed: Course A'],
            [$grace, 'Training completed by Ada Lovelace: Course A'],
            [$ada, 'Required training: Course B'],
        ], $this->newMails());
        $this->assertStringContainsString('Step 2 of 3 of the learning path &quot;Onboarding&quot; is now open.', $this->mailBody($ada, 'Required training: Course B'));
        $this->assertStringContainsString('href="https://academy.example.test/account/training"', $this->mailBody($ada, 'Training completed: Course A'));

        // day 14: B is finished, step 3 opens
        $this->day(14);
        $this->finish($learner, $b);
        $this->assertSame([
            [$ada, 'Training completed: Course B'],
            [$grace, 'Training completed by Ada Lovelace: Course B'],
            [$ada, 'Required training: Course C'],
        ], $this->newMails());
        $this->assertStringContainsString('Step 3 of 3 of the learning path &quot;Onboarding&quot; is now open.', $this->mailBody($ada, 'Required training: Course C'));

        // day 20: C is finished and so is the path
        $this->day(20);
        $this->finish($learner, $c);
        $this->assertSame([
            [$ada, 'Training completed: Course C'],
            [$grace, 'Training completed by Ada Lovelace: Course C'],
            [$ada, 'Learning path completed: Onboarding'],
        ], $this->newMails());
        $this->assertNotNull(PathEnrollment::query()->where('user_id', $learner->getKey())->firstOrFail()->completed_at);

        // reruns: the daily job long after the deadlines, and every event again
        $this->day(90);
        $this->process();
        $this->process();
        foreach ([$a, $b, $c] as $course) {
            $this->finish($learner, $course);
        }
        app(PathEnrollmentService::class)->advance($learner, $c);
        $this->assertSame([], $this->newMails());

        $this->assertSame(
            ['PathCompleted' => 1, 'PathEnrolled' => 1, 'TrainingAssigned' => 3, 'TrainingCompleted' => 3, 'TrainingDueSoon' => 1, 'TrainingOverdue' => 1],
            $this->inApp($learner->getKey()),
        );
        $this->assertSame(['TrainingCompletedForManager' => 3, 'TrainingEscalated' => 1], $this->inApp($manager->getKey()));
    }
}
