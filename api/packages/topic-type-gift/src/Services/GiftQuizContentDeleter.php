<?php

namespace Ulams\TopicTypeGift\Services;

use Illuminate\Database\Eloquent\Model;
use Ulams\Courses\Services\Contracts\TopicContentDeleter;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;

/**
 * Deletes a quiz with its topic, unless learners have answered it: answered questions are never
 * deleted (ADR 0033), so the quiz stays and is only detached from the removed topic.
 */
class GiftQuizContentDeleter implements TopicContentDeleter
{
    public function __construct(private readonly GiftQuestionServiceContract $questions)
    {
    }

    public function supports(Model $content): bool
    {
        return $content instanceof GiftQuiz;
    }

    public function delete(Model $content): void
    {
        /** @var GiftQuiz $content */
        if (QuizAttempt::query()->where('topic_gift_quiz_id', $content->getKey())->exists()) {
            return;
        }

        foreach ($content->allQuestions()->get() as $question) {
            $this->questions->delete($question->getKey());
        }

        $content->delete();
    }
}
