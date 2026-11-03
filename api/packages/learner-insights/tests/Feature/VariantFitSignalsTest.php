<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\CourseVariants\Contracts\FitSignals;
use Ulams\LearnerInsights\Models\LearnerStatus;
use Ulams\LearnerInsights\Scoring\Status;
use Ulams\LearnerInsights\Tests\TestCase;

/**
 * The two transparent rules behind the hint to switch course variant (ADR 0192): too hard (struggling at the start) and too
 * easy (first-try passes and a fraction of the cohort's time). They respect the module switch and never gate anything.
 */
class VariantFitSignalsTest extends TestCase
{
    /** @return array{0:Course,1:Topic[],2:Topic} three lessons: read + quiz in the first two, one read in the third; the quiz topic of module 1 last */
    private function course(): array
    {
        $course = Course::factory()->create(['status' => 'published']);
        $topics = [];
        $quiz = null;
        foreach ([1, 2, 3] as $order) {
            $lesson = Lesson::factory()->create(['course_id' => $course->getKey(), 'order' => $order, 'active' => true]);
            $topics[] = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true, 'order' => 1]);
            if ($order <= 2) {
                $q = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true, 'order' => 2]);
                $q->forceFill(['topicable_type' => (new GiftQuiz())->getMorphClass(), 'topicable_id' => 1])->saveQuietly();
                $topics[] = $q;
                $quiz = $q;
            }
        }

        return [$course, $topics, $quiz];
    }

    private function fit(): FitSignals
    {
        return $this->app->make(FitSignals::class);
    }

    private function cohort(Course $course, array $topics, int $learners, int $seconds): void
    {
        foreach (range(1, $learners) as $_) {
            $user = $this->learner($course);
            foreach (array_slice($topics, 0, 4) as $topic) {
                $this->finish($topic, $user, $seconds);
            }
        }
    }

    private function quizSignal($user, Course $course, Topic $topic, int $attempt, bool $passed): void
    {
        DB::table('learner_signals')->insert([
            'user_id' => $user->getKey(), 'course_id' => $course->getKey(), 'topic_id' => $topic->getKey(), 'type' => 'quiz.attempt_finished',
            'data' => json_encode(['attempt_no' => $attempt, 'passed' => $passed]), 'source' => 'gift', 'source_ref' => "t{$topic->getKey()}u{$user->getKey()}a{$attempt}",
            'occurred_at' => now(), 'recorded_at' => now(),
        ]);
    }

    /** A learner who finished the first two modules in `$seconds` each topic, with first-try quiz passes when asked. */
    private function fastLearner(Course $course, array $topics, int $seconds = 60, bool $firstTry = true)
    {
        $user = $this->learner($course);
        foreach (array_slice($topics, 0, 4) as $topic) {
            $this->finish($topic, $user, $seconds);
        }
        foreach ([$topics[1], $topics[3]] as $i => $quiz) {
            $this->quizSignal($user, $course, $quiz, $firstTry ? 1 : 2, true);
        }

        return $user;
    }

    public function testFirstTryPassesAndAFractionOfTheCohortTimeMeanTooEasy(): void
    {
        [$course, $topics] = $this->course();
        $this->cohort($course, $topics, 10, 600);
        $user = $this->fastLearner($course, $topics, 60);

        $signal = $this->fit()->assess($user->getKey(), $course->getKey());

        $this->assertSame('harder', $signal['direction']);
        $this->assertSame('variant_too_easy', $signal['reasons'][0]['rule']);
        $this->assertSame('insights.reason.variant_too_easy', $signal['reasons'][0]['messageKey']);
        $this->assertSame(2, $signal['reasons'][0]['params']['modules']);
        $this->assertStringContainsString('first 2 modules', $signal['reasons'][0]['message']);
    }

    public function testNoTooEasyWhenAQuizNeededASecondAttemptOrTheLearnerWasNotClearlyFaster(): void
    {
        [$course, $topics] = $this->course();
        $this->cohort($course, $topics, 10, 600);

        $this->assertNull($this->fit()->assess($this->fastLearner($course, $topics, 60, false)->getKey(), $course->getKey()), 'a second attempt');
        $this->assertNull($this->fit()->assess($this->fastLearner($course, $topics, 400)->getKey(), $course->getKey()), 'about the cohort median');
    }

    public function testNoTooEasyWithoutACohortOrBeforeTheFirstModulesAreDone(): void
    {
        [$course, $topics] = $this->course();
        $this->assertNull($this->fit()->assess($this->fastLearner($course, $topics, 60)->getKey(), $course->getKey()), 'nobody to compare with');

        $this->cohort($course, $topics, 10, 600);
        $partial = $this->learner($course);
        $this->finish($topics[0], $partial, 30);
        $this->assertNull($this->fit()->assess($partial->getKey(), $course->getKey()), 'the first modules are not finished');
    }

    public function testStrugglingAtTheStartMeansTooHardButNotLaterInTheCourse(): void
    {
        [$course, $topics] = $this->course();
        $user = $this->learner($course);
        $this->finish($topics[0], $user, 100);
        LearnerStatus::query()->updateOrCreate(['user_id' => $user->getKey(), 'course_id' => $course->getKey(), 'element_key' => ''], ['status' => Status::STRUGGLING, 'reasons' => [], 'scorer' => 'rules', 'scorer_version' => '1', 'since' => now(), 'evaluated_at' => now()]);

        $signal = $this->fit()->assess($user->getKey(), $course->getKey());
        $this->assertSame('easier', $signal['direction']);
        $this->assertSame('variant_too_hard', $signal['reasons'][0]['rule']);

        $this->finish($topics[4], $user, 100); // got through to module 3: the start was not the problem
        $this->assertNull($this->fit()->assess($user->getKey(), $course->getKey()));
    }

    public function testNothingWhenInsightsAreOffOrTheLearnerOptedOut(): void
    {
        [$course, $topics] = $this->course();
        $this->cohort($course, $topics, 10, 600);
        $user = $this->fastLearner($course, $topics, 60);
        $this->assertNotNull($this->fit()->assess($user->getKey(), $course->getKey()));

        DB::table('user_settings')->insert(['user_id' => $user->getKey(), 'key' => 'insights.enabled', 'value' => 'false']);
        $this->assertNull($this->fit()->assess($user->getKey(), $course->getKey()), 'the learner opted out');

        DB::table('user_settings')->where('user_id', $user->getKey())->delete();
        config(['learner_insights.enabled' => false]);
        $this->assertNull($this->fit()->assess($user->getKey(), $course->getKey()), 'the module is off for the tenant');
    }

    public function testACourseWithoutLessonsHasNothingToJudge(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $this->assertNull($this->fit()->assess($this->learner($course)->getKey(), $course->getKey()));
    }
}
