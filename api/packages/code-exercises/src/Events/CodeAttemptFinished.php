<?php

namespace Ulams\CodeExercises\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A learner finished a run of a code exercise's tests. Ids and numbers only; learner-insights turns it
 * into the `code.attempt_finished` signal (ADR 0057).
 */
class CodeAttemptFinished
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $topicId,
        public readonly int $attemptId,
        public readonly int $attemptNo,
        public readonly int $score,
        public readonly bool $passed,
        public readonly bool $verified,
    ) {
    }
}
