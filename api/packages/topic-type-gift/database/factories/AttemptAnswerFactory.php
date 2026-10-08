<?php

namespace EscolaLms\TopicTypeGift\Database\Factories;

use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttemptAnswerFactory extends Factory
{
    protected $model = AttemptAnswer::class;

    public function definition(): array
    {
        return [
            'topic_gift_question_id' => GiftQuestion::factory(),
            'topic_gift_quiz_attempt_id' => QuizAttempt::factory(),
            'answer' => $this->faker->text,
            'feedback' => $this->faker->text,
            'score' => $this->faker->numberBetween(0, 20),
            'graded_at' => Carbon::now(),
        ];
    }

    public function awaitingGrading(): self
    {
        return $this->state(fn (array $attributes) => ['graded_at' => null]);
    }
}
