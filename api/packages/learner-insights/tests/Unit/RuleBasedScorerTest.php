<?php

namespace Ulams\LearnerInsights\Tests\Unit;

use Ulams\Core\Tests\TestCase;
use Ulams\LearnerInsights\Scoring\RuleBasedScorer;
use Ulams\LearnerInsights\Scoring\Status;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\Support\Ctx;

class RuleBasedScorerTest extends TestCase
{
    private function assess(Ctx $ctx)
    {
        return (new RuleBasedScorer())->assess($ctx->build());
    }

    private function rules(array $assessment, string $element): array
    {
        return array_map(fn ($r) => $r->rule, $assessment[$element]->reasons);
    }

    public function testASteadyLearnerIsOnTrack(): void
    {
        $a = $this->assess(Ctx::learner()->signal(SignalType::TOPIC_PROGRESSED, 'topic:1', 1, [], 60)->signal(SignalType::TOPIC_COMPLETED, 'topic:1', 1));

        $this->assertSame(Status::ON_TRACK, $a->course);
        $this->assertSame([], $a->elements);
        $this->assertSame([], $a->reasons);
    }

    public function testFinishedAndUnenrolledLearnersAreAlwaysOnTrack(): void
    {
        $ctx = fn () => Ctx::learner()->signal(SignalType::TOPIC_OPENED, 'topic:1', 20);
        $this->assertSame(Status::ON_TRACK, $this->assess($ctx()->finished())->course);
        $this->assertSame(Status::ON_TRACK, $this->assess($ctx()->unenrolled())->course);
    }

    public function testFailedAnswersStopAtTheThresholdAndRestartAfterACorrectAnswer(): void
    {
        $one = $this->assess(Ctx::learner()->wrongAnswer('question:3', 2));
        $this->assertSame([], $one->elements, 'one wrong answer is below the threshold of 2');

        $two = $this->assess(Ctx::learner()->wrongAnswer('question:3', 3)->wrongAnswer('question:3', 2));
        $this->assertSame(Status::STRUGGLING, $two->course);
        $reason = $two->elements['question:3']->reasons[0];
        $this->assertSame('failed_attempts', $reason->rule);
        $this->assertSame('insights.reason.failed_attempts', $reason->messageKey);
        $this->assertSame(['count' => 2, 'label' => 'Rebasing onto main'], $reason->params);

        $recovered = $this->assess(Ctx::learner()->wrongAnswer('question:3', 4)->wrongAnswer('question:3', 3)->rightAnswer('question:3', 2));
        $this->assertSame(Status::ON_TRACK, $recovered->course);
    }

    public function testFailedQuizAttemptsCountUntilThePass(): void
    {
        $fail = fn (Ctx $c, int $d) => $c->signal(SignalType::QUIZ_ATTEMPT_FINISHED, 'topic:5', $d, ['passed' => false, 'attempt_no' => 1], 0.2);
        $a = $this->assess($fail($fail(Ctx::learner(), 3), 2));
        $this->assertSame(['failed_attempts'], $this->rules($a->elements, 'topic:5'));
        $this->assertSame('insights.reason.failed_quiz_attempts', $a->elements['topic:5']->reasons[0]->messageKey);

        $passed = Ctx::learner();
        $fail($passed, 4);
        $fail($passed, 3);
        $passed->signal(SignalType::QUIZ_ATTEMPT_FINISHED, 'topic:5', 2, ['passed' => true], 0.9);
        $this->assertSame([], $this->assess($passed)->elements);
    }

    public function testInactivityIsAtRiskExactlyAtTheThreshold(): void
    {
        $at = fn (int $days) => $this->assess(Ctx::learner()->signal(SignalType::TOPIC_PROGRESSED, 'topic:1', $days, [], 30));

        $this->assertSame(Status::ON_TRACK, $at(6)->course);
        $seven = $at(7);
        $this->assertSame(Status::AT_RISK, $seven->course);
        $this->assertSame('inactivity', $seven->reasons[0]->rule);
        $this->assertSame(['days' => 7], $seven->reasons[0]->params);
    }

    public function testASlowElementNeedsEnoughLearnersInTheCohortAndStrictlyMoreThanTheFactor(): void
    {
        $spent = fn (Ctx $c) => $c->signal(SignalType::TOPIC_PROGRESSED, 'topic:2', 1, [], 3100);

        $small = $this->assess($spent(Ctx::learner())->cohort('topic:2', 1000, 9));
        $this->assertSame([], $small->elements, 'a cohort below 10 learners gives no slow rule');

        $exact = $this->assess(Ctx::learner()->signal(SignalType::TOPIC_PROGRESSED, 'topic:2', 1, [], 3000)->cohort('topic:2', 1000, 10));
        $this->assertSame([], $exact->elements, 'exactly 3x is not above 3x');

        $slow = $this->assess($spent(Ctx::learner())->cohort('topic:2', 1000, 10));
        $this->assertSame(['slow_element'], $this->rules($slow->elements, 'topic:2'));
        $this->assertSame(3, $slow->elements['topic:2']->reasons[0]->params['factor']);

        $done = $this->assess($spent(Ctx::learner())->signal(SignalType::TOPIC_COMPLETED, 'topic:2', 1)->cohort('topic:2', 1000, 10));
        $this->assertSame([], $done->elements, 'a finished element is not struggling');
    }

    public function testAnOpenedElementIsAbandonedOnlyWhileTheLearnerWorksElsewhere(): void
    {
        $alone = $this->assess(Ctx::learner()->signal(SignalType::TOPIC_OPENED, 'topic:4', 6));
        $this->assertSame([], $alone->elements, 'no activity elsewhere: the learner is simply away (inactivity covers it)');

        $busy = $this->assess(Ctx::learner()->signal(SignalType::TOPIC_OPENED, 'topic:4', 6)->signal(SignalType::TOPIC_PROGRESSED, 'topic:9', 1, [], 20));
        $this->assertSame(['abandoned_element'], $this->rules($busy->elements, 'topic:4'));

        $recent = $this->assess(Ctx::learner()->signal(SignalType::TOPIC_OPENED, 'topic:4', 4)->signal(SignalType::TOPIC_PROGRESSED, 'topic:9', 1, [], 20));
        $this->assertSame([], $recent->elements, 'opened 4 days ago is below 5');
    }

    public function testRepetitionAloneIsLowWeightButAddsToAnotherRule(): void
    {
        $replays = fn (Ctx $c) => $c->signal(SignalType::MEDIA_REPLAYED, 'topic:6', 1)->signal(SignalType::MEDIA_REPLAYED, 'topic:6', 1)->signal(SignalType::MEDIA_SEEKED_BACK, 'topic:6', 0);

        $alone = $this->assess($replays(Ctx::learner()));
        $this->assertSame(Status::ON_TRACK, $alone->course, 'repetition alone does not change the course status');
        $this->assertTrue($alone->elements['topic:6']->lowWeight);

        $combined = $this->assess($replays(Ctx::learner())->wrongAnswer('topic:6', 3)->wrongAnswer('topic:6', 2));
        $this->assertSame(Status::STRUGGLING, $combined->course);
        $this->assertFalse($combined->elements['topic:6']->lowWeight);
        $this->assertEqualsCanonicalizing(['failed_attempts', 'repetition'], $this->rules($combined->elements, 'topic:6'));
    }

    public function testManyStrugglingElementsPlusThreeInactiveDaysAreAtRisk(): void
    {
        $c = Ctx::learner();
        foreach ([1, 2, 3] as $q) {
            $c->wrongAnswer("question:$q", 5, $q)->wrongAnswer("question:$q", 4, $q);
        }
        $a = $this->assess($c);

        $this->assertSame(Status::AT_RISK, $a->course);
        $this->assertSame('struggling_elements', $a->reasons[0]->rule);

        $fresh = Ctx::learner();
        foreach ([1, 2, 3] as $q) {
            $fresh->wrongAnswer("question:$q", 1, $q)->wrongAnswer("question:$q", 0, $q);
        }
        $this->assertSame(Status::STRUGGLING, $this->assess($fresh)->course, 'still active: struggling, not at risk');
    }

    public function testThresholdsAreConfigurablePerCourseAndRulesCanBeSwitchedOff(): void
    {
        $a = $this->assess(Ctx::learner()->signal(SignalType::TOPIC_PROGRESSED, 'topic:1', 5, [], 30)->override('inactivity', 'inactive_days', 5));
        $this->assertSame(Status::AT_RISK, $a->course);

        $off = $this->assess(Ctx::learner()->signal(SignalType::TOPIC_PROGRESSED, 'topic:1', 20, [], 30)->override('inactivity', 'enabled', false));
        $this->assertSame(Status::ON_TRACK, $off->course);

        $strict = $this->assess(Ctx::learner()->wrongAnswer('question:3', 2)->override('failed_attempts', 'failed_answers', 1));
        $this->assertSame(Status::STRUGGLING, $strict->course);
    }

    public function testTheScorerIdentifiesItself(): void
    {
        $scorer = new RuleBasedScorer();
        $this->assertSame('rules', $scorer->key());
        $this->assertNotSame('', $scorer->version());
    }
}
