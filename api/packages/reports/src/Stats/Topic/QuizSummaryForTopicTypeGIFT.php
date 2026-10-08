<?php

namespace EscolaLms\Reports\Stats\Topic;

use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Support\Collection;

class QuizSummaryForTopicTypeGIFT extends AbstractTopicStat
{
    public function calculate(): array
    {
        if ($this->topic->topicable_type !== GiftQuiz::class) {
            return [];
        }

        $quiz = $this->topic->topicable;

        assert($quiz instanceof GiftQuiz);

        $questions = $quiz->questions;
        $max_score = $questions->sum('score');

        $attempts = QuizAttempt::query()
            ->where('topic_gift_quiz_id', $quiz->getKey())
            ->with('answers')
            ->orderBy('user_id', 'asc')
            ->orderBy('started_at', 'asc')
            ->get();

        $user_attempt_count = [];

        $headers = [
            'user_id' => __('User'),
            'email' => __('Email'),
            'name' => __('Name'),
            'attempt' => __('Attempt'),
            'attempt_date' => __('Attempt Date'),
            'attempt_time' => __('Time'),
        ];
        foreach ($quiz->questions as $question) {
            assert($question instanceof GiftQuestion);
            $headers['question_' . $question->getKey()] = 'Question ' . $question->order;
        }
        $headers['summary'] = __('Summary');

        $result = $attempts->map(function (QuizAttempt $attempt) use (&$user_attempt_count, $max_score) {
            $user_attempt_count[$attempt->user_id] = ($user_attempt_count[$attempt->user_id] ?? 0) + 1;

            $questions_subarray = [];
            $user_score = 0;
            foreach ($attempt->answers as $key => $answer) {
                assert($answer instanceof AttemptAnswer);
                $questions_subarray['question_' . $answer->topic_gift_question_id] = $answer->score;
                $user_score += $answer->score;
            }

            return array_merge(
                [
                    'user_id' => $attempt->user_id,
                    'email' => $attempt->user->email,
                    'name' => $attempt->user->name,
                    'attempt' => $user_attempt_count[$attempt->user_id],
                    'attempt_date' => $attempt->started_at,
                    'attempt_time' => $attempt->end_at->diffInSeconds($attempt->started_at),
                ],
                $questions_subarray,
                [
                    'summary' => $user_score / $max_score,
                ]
            );
        });

        assert($result instanceof Collection);

        return array_merge(
            [$headers],
            $result->toArray(),
        );
    }
}
