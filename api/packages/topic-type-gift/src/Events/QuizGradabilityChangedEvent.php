<?php

namespace EscolaLms\TopicTypeGift\Events;

use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted whenever a quiz's counts_to_grade flag flips, in either direction.
 * Consumed (in pcg-grades) to regenerate or delete the journal partial grade(s)
 * derived from this quiz.
 */
class QuizGradabilityChangedEvent
{
    use Dispatchable, SerializesModels;

    public GiftQuiz $quiz;

    public function __construct(GiftQuiz $quiz)
    {
        $this->quiz = $quiz;
    }

    public function getQuiz(): GiftQuiz
    {
        return $this->quiz;
    }

    public function countsToGrade(): bool
    {
        return (bool) $this->quiz->counts_to_grade;
    }
}
