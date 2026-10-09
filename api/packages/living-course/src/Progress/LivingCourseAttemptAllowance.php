<?php

namespace Ulams\LivingCourse\Progress;

use Ulams\Courses\Models\Topic;
use Ulams\LivingCourse\Models\LearnerNotice;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptAllowanceContract;

/** ADR 0033: one extra attempt for a learner with an open re-attempt notice on the quiz, even when max_attempts is reached. */
final class LivingCourseAttemptAllowance implements QuizAttemptAllowanceContract
{
    public function extraAttempts(int $userId, int $quizId): int
    {
        $topicIds = Topic::query()->where('topicable_type', (new GiftQuiz())->getMorphClass())->where('topicable_id', $quizId)->pluck('id');
        if ($topicIds->isEmpty()) {
            return 0;
        }

        return LearnerNotice::query()->where('user_id', $userId)->where('kind', 'question_reattempt')->where('status', 'open')->whereIn('topic_id', $topicIds)->exists() ? 1 : 0;
    }
}
