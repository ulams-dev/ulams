<?php

namespace Ulams\Tutor\Services;

use Illuminate\Support\Facades\DB;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\Tutor\Support\GiftAnswers;

/**
 * Knows whether a learner has a quiz attempt open in a course and which answers would give it away
 * (plan 8.2). An attempt is open until it ends or is submitted; one that was never submitted and has
 * no time limit stays open, which errs on the side of not helping with quiz answers.
 */
class AttemptGuard
{
    /** @return array{active:bool,correct:string[]} */
    public function check(int $userId, int $courseId): array
    {
        $morph = (new GiftQuiz())->getMorphClass();
        $quizIds = DB::table('topics')->join('lessons', 'lessons.id', '=', 'topics.lesson_id')->where('lessons.course_id', $courseId)->where('topics.topicable_type', $morph)->pluck('topics.topicable_id');
        if ($quizIds->isEmpty()) {
            return ['active' => false, 'correct' => []];
        }
        $open = QuizAttempt::query()->where('user_id', $userId)->whereIn('topic_gift_quiz_id', $quizIds)->active()->pluck('topic_gift_quiz_id')->unique();
        if ($open->isEmpty()) {
            return ['active' => false, 'correct' => []];
        }

        $correct = [];
        foreach (GiftQuestion::query()->whereIn('topic_gift_quiz_id', $open)->get() as $question) {
            $correct = array_merge($correct, GiftAnswers::correct((string) $question->value));
        }

        return ['active' => true, 'correct' => array_values(array_unique($correct))];
    }
}
