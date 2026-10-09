<?php

namespace Ulams\Courses\Tests\Services;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Services\Contracts\CourseCompletionGuardContract;
use Ulams\Courses\Services\Contracts\ProgressServiceContract;
use Ulams\Courses\Tests\Models\User;
use Ulams\Courses\Tests\TestCase;

/** ADR 0033: a learner who finished a course keeps `finished` when the guard says so. */
class CourseCompletionGuardTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:Course,2:Topic} a learner who finished a one-topic course that then gained a topic */
    private function finishedThenExtended(): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $done = Topic::factory()->create(['active' => true, 'lesson_id' => $lesson->getKey()]);
        $user->courses()->sync([$course->getKey() => ['finished' => true]]);
        CourseProgress::create(['user_id' => $user->getKey(), 'topic_id' => $done->getKey(), 'status' => ProgressStatus::COMPLETE]);
        Topic::factory()->create(['active' => true, 'lesson_id' => $lesson->getKey()]);

        return [$user, $course, $done];
    }

    private function finished(User $user, Course $course): bool
    {
        return $user->refresh()->finishedCourse($course->getKey());
    }

    public function testTheDefaultKeepsTheUpstreamBehaviourAndClearsFinishedWhenATopicWasAdded(): void
    {
        [$user, $course, $done] = $this->finishedThenExtended();
        $this->assertInstanceOf(\Ulams\Courses\Services\AlwaysMayUnfinish::class, app(CourseCompletionGuardContract::class));
        $this->assertTrue($this->finished($user, $course));

        app(ProgressServiceContract::class)->ping($user, $done);

        $this->assertFalse($this->finished($user, $course));
    }

    public function testAGuardThatRefusesKeepsFinishedOnPingAndOnUpdate(): void
    {
        [$user, $course, $done] = $this->finishedThenExtended();
        $this->app->singleton(CourseCompletionGuardContract::class, fn () => new class implements CourseCompletionGuardContract {
            public function mayUnfinish(Course $course, CoreUser $user): bool
            {
                return false;
            }
        });
        $this->app->forgetInstance(ProgressServiceContract::class);

        app(ProgressServiceContract::class)->ping($user, $done);
        $this->assertTrue($this->finished($user, $course));

        app(ProgressServiceContract::class)->update($course, $user, []);
        $this->assertTrue($this->finished($user, $course));
        $this->assertSame(ProgressStatus::COMPLETE, (int) CourseProgress::query()->where('user_id', $user->getKey())->where('topic_id', $done->getKey())->value('status'), 'the completed topic stays completed');
    }
}
