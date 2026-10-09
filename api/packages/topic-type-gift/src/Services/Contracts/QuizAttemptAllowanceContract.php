<?php

namespace Ulams\TopicTypeGift\Services\Contracts;

/**
 * Attempts a learner may take beyond the quiz's `max_attempts`. The default grants none; Living
 * Course grants one extra attempt for a quiz whose correct answer was corrected (ADR 0033).
 */
interface QuizAttemptAllowanceContract
{
    public function extraAttempts(int $userId, int $quizId): int;
}
