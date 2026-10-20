<?php

namespace Ulams\LearnerInsights\Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Models\LearnerStatus;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\TestCase;

/**
 * A small DSL for synthetic learner journeys: a learner acts on named topics and questions on
 * numbered days, the nightly sweep runs when asked, and the test asserts statuses and events.
 *
 *   Journey::learner($this, $course, ['t1' => $topic])->day(1)->complete('t1')->day(8)->nightly()->assertCourse('at_risk');
 */
final class Journey
{
    private int $ref = 0;

    private CarbonImmutable $start;

    private function __construct(private readonly TestCase $t, public readonly Course $course, public readonly mixed $user, private readonly array $topics)
    {
        $this->start = CarbonImmutable::parse(Ctx::NOW)->startOfDay()->addHours(9);
        $this->t->travelTo($this->start);
    }

    /** @param array<string,Topic> $topics */
    public static function learner(TestCase $t, Course $course, array $topics, mixed $user = null): self
    {
        return new self($t, $course, $user ?? $t->learnerFor($course), $topics);
    }

    public function day(int $n): self
    {
        $this->t->travelTo($this->start->addDays($n));

        return $this;
    }

    public function open(string $topic, bool $revisit = false): self
    {
        return $this->emit(SignalType::TOPIC_OPENED, 'progress', $topic, null, null, ['revisit' => $revisit]);
    }

    public function spend(string $topic, int $seconds): self
    {
        return $this->emit(SignalType::TOPIC_PROGRESSED, 'progress', $topic, null, (float) $seconds, ['status' => 2]);
    }

    public function complete(string $topic): self
    {
        return $this->emit(SignalType::TOPIC_COMPLETED, 'progress', $topic);
    }

    public function answer(string $topic, int $question, bool $correct): self
    {
        return $this->emit(SignalType::QUIZ_ANSWER, 'gift', $topic, $question, $correct ? 1.0 : 0.0, ['question_id' => $question, 'correct' => $correct]);
    }

    public function failQuiz(string $topic): self
    {
        return $this->emit(SignalType::QUIZ_ATTEMPT_FINISHED, 'gift', $topic, null, 0.3, ['passed' => false, 'attempt_no' => 1]);
    }

    public function passQuiz(string $topic): self
    {
        return $this->emit(SignalType::QUIZ_ATTEMPT_FINISHED, 'gift', $topic, null, 0.9, ['passed' => true, 'attempt_no' => 2]);
    }

    /** The scheduled `learner-insights:evaluate` run. */
    public function nightly(): self
    {
        $this->t->artisan('learner-insights:evaluate')->assertSuccessful();

        return $this;
    }

    public function courseStatus(): ?string
    {
        return LearnerStatus::query()->where('user_id', $this->user->getKey())->where('course_id', $this->course->getKey())->where('element_key', '')->value('status');
    }

    public function elementStatus(string $elementKey): ?string
    {
        return LearnerStatus::query()->where('user_id', $this->user->getKey())->where('course_id', $this->course->getKey())->where('element_key', $elementKey)->value('status');
    }

    public function assertCourse(string $status): self
    {
        $this->t->assertSame($status, $this->courseStatus() ?? 'on_track', 'course status');

        return $this;
    }

    public function assertElement(string $elementKey, ?string $status): self
    {
        $this->t->assertSame($status, $this->elementStatus($elementKey), "status of $elementKey");

        return $this;
    }

    public function assertEvent(string $event, int $times = 1): self
    {
        Event::assertDispatchedTimes($event, $times);

        return $this;
    }

    public function topicId(string $name): int
    {
        return (int) $this->topics[$name]->getKey();
    }

    public function topicKey(string $name): string
    {
        return 'topic:' . $this->topics[$name]->getKey();
    }

    private function emit(string $type, string $source, string $topic, ?int $question = null, ?float $value = null, array $data = []): self
    {
        app(SignalRecorder::class)->record(new SignalData(
            (int) $this->user->getKey(), $type, $source, 'journey:' . $this->user->getKey() . ':' . ++$this->ref, CarbonImmutable::now(),
            (int) $this->course->getKey(), (int) $this->topics[$topic]->getKey(), $question, $value, $data,
        ));

        return $this;
    }
}
