<?php

namespace Ulams\LivingCourse\Progress;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Contracts\RemovalPolicy;
use Ulams\Courses\Models\Topic;

/**
 * ADR 0033: an update never deletes learner data. Topics and lessons that learners have progress
 * on are deactivated instead of deleted; GIFT questions that learners answered are archived.
 * Without learner data the element is deleted as in Phase 2.
 */
final class LivingCourseRemovalPolicy implements RemovalPolicy
{
    public function shouldDelete(string $entityType, int $entityId): bool
    {
        return match ($entityType) {
            'topic', 'quiz_topic' => !$this->topicHasLearnerData($entityId),
            'lesson' => !Topic::query()->where('lesson_id', $entityId)->get(['id'])->contains(fn (Topic $t) => $this->topicHasLearnerData($t->getKey())),
            'gift_question' => !DB::table('topic_gift_attempt_answers')->where('topic_gift_question_id', $entityId)->exists(),
            default => true,
        };
    }

    private function topicHasLearnerData(int $topicId): bool
    {
        if (DB::table('course_progress')->where('topic_id', $topicId)->exists()) {
            return true;
        }
        $topic = Topic::query()->find($topicId);
        if ($topic !== null && str_contains((string) $topic->topicable_type, 'GiftQuiz')) {
            return DB::table('topic_gift_quiz_attempts')->where('topic_gift_quiz_id', $topic->topicable_id)->exists();
        }

        return false;
    }
}
