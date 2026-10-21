<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Models\Remediation;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\Support\ModelRequests;
use Ulams\LearnerInsights\Tests\TestCase;

/**
 * "Signals never reach an LLM" (CLAUDE.md non-negotiables). A learner with a recognisable name and
 * e-mail address struggles in two courses; every request that learner's struggle makes the model
 * receive (remediation, grounding check, reflection feedback) is read as plain text and must hold
 * no id, name, address, other course, signal type or status of the learner, and every logged call
 * has no user. The learner's own words in a reflection are the one thing allowed through, wrapped
 * as untrusted text.
 */
class LlmMinimisationTest extends TestCase
{
    private const FIRST = 'Zeldaquill';

    private const LAST = 'Featherstone';

    private const EMAIL = 'zeldaquill.featherstone@learners.example.test';

    private const OTHER_COURSE = 'Zebra Quantum Pottery Masterclass';

    private Course $course;

    private int $topicId;

    private int $questionId;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $session = $this->toApplied($this->author());
        $this->course = Course::query()->findOrFail($session->course_id);
        $this->course->update(['status' => 'published']);
        $question = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'gift_question')->orderBy('id')->firstOrFail();
        $this->questionId = (int) $question->entity_id;
        $quiz = \Ulams\TopicTypeGift\Models\GiftQuestion::query()->findOrFail($this->questionId)->topic_gift_quiz_id;
        $this->topicId = (int) Topic::query()->where('topicable_type', (new \Ulams\TopicTypeGift\Models\GiftQuiz())->getMorphClass())->where('topicable_id', $quiz)->firstOrFail()->getKey();
    }

    private function identifiableLearner()
    {
        $user = $this->student();
        $user->forceFill(['first_name' => self::FIRST, 'last_name' => self::LAST, 'email' => self::EMAIL])->save();
        $user->courses()->attach($this->course->getKey());
        $other = Course::factory()->create(['status' => 'published', 'title' => self::OTHER_COURSE]);
        $user->courses()->attach($other->getKey());
        // history in the other course: a learner who struggles elsewhere must not leak that either
        foreach ([1, 2] as $ignored) {
            $this->answer($user, $other->getKey(), null, 91);
        }

        return $user;
    }

    private function answer($user, int $courseId, ?int $topicId, int $questionId): void
    {
        app(SignalRecorder::class)->record(new SignalData(
            $user->getKey(), SignalType::QUIZ_ANSWER, 'gift', 'min-test:' . ++$this->n, now(), $courseId, $topicId, $questionId, 0.0, ['question_id' => $questionId, 'correct' => false],
        ));
    }

    private function struggle($user): void
    {
        foreach ([1, 2] as $ignored) {
            $this->answer($user, $this->course->getKey(), $this->topicId, $this->questionId);
        }
    }

    /** @param string[] $extra */
    private function assertClean(string $task, array $extra = []): void
    {
        $sent = array_values(array_filter($this->fake()->sent(), fn ($r) => $r->task === $task));
        $this->assertNotEmpty($sent, "no $task request was recorded, the test would pass without checking anything");
        $forbidden = [self::FIRST, self::LAST, self::EMAIL, 'learners.example.test', self::OTHER_COURSE, ...ModelRequests::learnerVocabulary(), ...$extra];
        foreach ($sent as $request) {
            $text = ModelRequests::text($request);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsStringIgnoringCase($needle, $text, "a $task request carries \"$needle\"");
            }
        }
    }

    public function testTheRemediationRequestsCarryNothingOfTheLearner(): void
    {
        $user = $this->identifiableLearner();
        $this->struggle($user);

        $this->assertSame(2, Remediation::query()->count());
        $this->assertClean('remediation');
        // the grounding check of the generated help is a model call of its own
        foreach ($this->fake()->sent() as $request) {
            $text = ModelRequests::text($request);
            $this->assertStringNotContainsString(self::EMAIL, $text, "{$request->task} carries the address");
            $this->assertStringNotContainsString(self::LAST, $text, "{$request->task} carries the name");
        }
    }

    public function testTheReflectionRequestHoldsOnlyTheSourceAndTheLearnersOwnWords(): void
    {
        config(['learner_insights.remediation.reflections_per_day' => 5]);
        $user = $this->identifiableLearner();
        $this->struggle($user);
        $id = $this->actingAs($user, 'api')->getJson("/api/learner-insights/courses/{$this->course->getKey()}/topics/{$this->topicId}/support")->json('data.items.0.assignmentId');

        $this->actingAs($user, 'api')->postJson("/api/learner-insights/assignments/$id/reflection", ['text' => 'Brewing is about the ratio of water to coffee.'])->assertOk();

        $this->assertClean('reflection_feedback');
    }

    public function testEveryLoggedCallHasNoUser(): void
    {
        config(['learner_insights.remediation.reflections_per_day' => 5]);
        $user = $this->identifiableLearner();
        $this->struggle($user);
        $id = $this->actingAs($user, 'api')->getJson("/api/learner-insights/courses/{$this->course->getKey()}/topics/{$this->topicId}/support")->json('data.items.0.assignmentId');
        $this->actingAs($user, 'api')->postJson("/api/learner-insights/assignments/$id/reflection", ['text' => 'Brewing is about the ratio of water to coffee.'])->assertOk();

        $logged = AiCall::query()->whereIn('task', ['remediation', 'reflection_feedback'])->get();
        $this->assertGreaterThanOrEqual(3, $logged->count());
        foreach ($logged as $call) {
            $this->assertNull($call->user_id, "{$call->task} was logged with a user");
            $this->assertStringNotContainsString(self::EMAIL, json_encode($call->getAttributes()));
        }
    }

    public function testASecondLearnerWithTheSamePatternMakesNoCallAtAll(): void
    {
        $this->struggle($this->identifiableLearner());
        $calls = count($this->fake()->sent());
        $this->assertGreaterThan(0, $calls);

        $second = $this->student();
        $second->courses()->attach($this->course->getKey());
        $this->struggle($second);

        $this->assertCount($calls, $this->fake()->sent(), 'help is generated per element and difficulty, not per learner');
    }
}
