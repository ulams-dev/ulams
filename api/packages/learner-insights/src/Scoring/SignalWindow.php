<?php

namespace Ulams\LearnerInsights\Scoring;

use Illuminate\Support\Collection;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Signals\SignalType;

/** The learner's signals in one course over the last N days, oldest first. */
final class SignalWindow
{
    /** Signals that mean the learner did something in the course (the rest are about interventions). */
    private const ACTIVITY = [
        SignalType::COURSE_STARTED, SignalType::TOPIC_OPENED, SignalType::TOPIC_PROGRESSED, SignalType::TOPIC_COMPLETED,
        SignalType::QUIZ_ATTEMPT_STARTED, SignalType::QUIZ_ATTEMPT_FINISHED, SignalType::QUIZ_ANSWER,
        SignalType::SCORM_STATUS, SignalType::XAPI_RESULT, SignalType::H5P_RESULT,
        SignalType::MEDIA_REPLAYED, SignalType::MEDIA_SEEKED_BACK, SignalType::TUTOR_QUESTION, SignalType::CODE_ATTEMPT_FINISHED,
    ];

    /** @param Collection<int,Signal> $signals */
    public function __construct(public readonly Collection $signals)
    {
    }

    /** @return Collection<int,Signal> */
    public function ofType(string ...$types): Collection
    {
        return $this->signals->filter(fn (Signal $s) => in_array($s->type, $types, true))->values();
    }

    /** @return Collection<int,Signal> */
    public function filter(callable $keep): Collection
    {
        return $this->signals->filter($keep)->values();
    }

    /** @return Collection<int,Signal> */
    public function activity(): Collection
    {
        return $this->ofType(...self::ACTIVITY);
    }

    public function lastActivity(): ?\Carbon\CarbonInterface
    {
        return $this->activity()->max('occurred_at');
    }

    public function completed(string $elementKey): bool
    {
        return $this->signals->contains(fn (Signal $s) => $s->element_key === $elementKey && $s->type === SignalType::TOPIC_COMPLETED);
    }
}
