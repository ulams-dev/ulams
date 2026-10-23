<?php

namespace Ulams\CourseVariants\Tests\Feature;

use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\CourseProgress;
use Ulams\CourseVariants\Contracts\TopicCorrespondence;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Services\AssignmentService;
use Ulams\CourseVariants\Tests\Support\FakeCorrespondence;
use Ulams\CourseVariants\Tests\TestCase;

/**
 * Switching variant carries completion over for topics that are the same content in both courses, through the courses
 * progress service; nothing else moves and the old course keeps everything (ADR 0192).
 */
class ProgressCarryOverTest extends TestCase
{
    private function complete($learner, $topic): void
    {
        CourseProgress::query()->updateOrCreate(['user_id' => $learner->getKey(), 'topic_id' => $topic->getKey()], ['status' => ProgressStatus::COMPLETE, 'seconds' => 60, 'finished_at' => now()]);
    }

    private function progressOf($learner, $topic): ?int
    {
        return CourseProgress::query()->where('user_id', $learner->getKey())->where('topic_id', $topic->getKey())->value('status');
    }

    public function testOnlyCompletedTopicsWithIdenticalContentAreCopied(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family(['expert']);
        $expert = $variants['expert'];
        [$a1, $a2, $a3] = $this->topics($default, 3);
        [$b1, $b2, $b3] = $this->topics($expert, 3);
        // topics 1 and 2 are the same content in both courses; topic 3 differs (rewritten for experts)
        $this->app->instance(TopicCorrespondence::class, new FakeCorrespondence(["{$default->getKey()}:{$expert->getKey()}" => [$a1->getKey() => $b1->getKey(), $a2->getKey() => $b2->getKey()]]));
        $learner = $this->student();
        $this->buy($learner, $default);
        $this->complete($learner, $a1);
        $this->complete($learner, $a3); // done, but has no counterpart

        $result = $this->app->make(AssignmentService::class)->choose($learner->getKey(), $group, $expert->getKey(), Assignment::SELF);

        $this->assertSame(1, $result['carried']);
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressOf($learner, $b1));
        $this->assertNotSame(ProgressStatus::COMPLETE, $this->progressOf($learner, $b2), 'not done in the old course, so not done here');
        $this->assertNotSame(ProgressStatus::COMPLETE, $this->progressOf($learner, $b3), 'different content stays open');
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressOf($learner, $a1), 'the old course keeps its progress');
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressOf($learner, $a3));
    }

    public function testSwitchingBackRestoresTheOldProgressAndCarriesNewWork(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family(['expert']);
        $expert = $variants['expert'];
        [$a1, $a2] = $this->topics($default, 2);
        [$b1, $b2] = $this->topics($expert, 2);
        $this->app->instance(TopicCorrespondence::class, new FakeCorrespondence([
            "{$default->getKey()}:{$expert->getKey()}" => [$a1->getKey() => $b1->getKey(), $a2->getKey() => $b2->getKey()],
            "{$expert->getKey()}:{$default->getKey()}" => [$b1->getKey() => $a1->getKey(), $b2->getKey() => $a2->getKey()],
        ]));
        $learner = $this->student();
        $this->buy($learner, $default);
        $service = $this->app->make(AssignmentService::class);
        $this->complete($learner, $a1);
        $service->choose($learner->getKey(), $group, $expert->getKey(), Assignment::SELF);
        $this->complete($learner, $b2); // new work in the expert course

        $back = $service->choose($learner->getKey(), $group, $default->getKey(), Assignment::SELF);

        $this->assertSame(1, $back['carried'], 'only the topic that was not complete yet');
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressOf($learner, $a1));
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressOf($learner, $a2));
        $this->assertSame(ProgressStatus::COMPLETE, $this->progressOf($learner, $b1), 'and the expert course still has what was carried into it');
    }

    public function testWithoutACorrespondenceNothingIsCarried(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family(['expert']);
        [$a1] = $this->topics($default, 1);
        $this->topics($variants['expert'], 1);
        $learner = $this->student();
        $this->buy($learner, $default);
        $this->complete($learner, $a1);

        $result = $this->app->make(AssignmentService::class)->choose($learner->getKey(), $group, $variants['expert']->getKey(), Assignment::SELF);

        $this->assertSame(0, $result['carried']);
    }
}
