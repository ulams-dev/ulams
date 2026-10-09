<?php

namespace Ulams\TopicTypeGift\Services;

use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptAllowanceContract;

/** The behaviour before Living Course: `max_attempts` is a hard limit. */
class NoExtraAttempts implements QuizAttemptAllowanceContract
{
    public function extraAttempts(int $userId, int $quizId): int
    {
        return 0;
    }
}
