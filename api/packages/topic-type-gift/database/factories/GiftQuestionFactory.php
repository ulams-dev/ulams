<?php

namespace EscolaLms\TopicTypeGift\Database\Factories;

use EscolaLms\TopicTypeGift\Enum\QuestionTypeEnum;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Database\Eloquent\Factories\Factory;

class GiftQuestionFactory extends Factory
{
    protected $model = GiftQuestion::class;

    public function definition(): array
    {
        $randomElement = $this->faker->randomElement($this->getExamples());

        return [
            'value' => $randomElement['question'],
            'type' => $randomElement['type'],
            'score' => $this->faker->numberBetween(1, 20),
            'topic_gift_quiz_id' => GiftQuiz::factory(),
        ];
    }

    private function getExamples(): array
    {
        return [
            [
                'question' => 'Who\'s buried in Grant\'s tomb?{~Grant ~Jefferson =no one}',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            ],
            [
                'question' => 'Grant is {~buried =entombed ~living} in Grant\'s tomb.',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            ],
            [
                'question' => 'Grant is buried in Grant\'s tomb.{FALSE}',
                'type' => QuestionTypeEnum::TRUE_FALSE,
            ],
            [
                'question' => 'Who\'s buried in Grant\'s tomb?{=no one =nobody}',
                'type' => QuestionTypeEnum::SHORT_ANSWERS,
            ],
            [
                'question' => 'When was Ulysses S. Grant born?{#1822:1}',
                'type' => QuestionTypeEnum::NUMERICAL_QUESTION,
            ],
            [
                'question' => 'When was Ulysses S. Grant born?{#1822:1}',
                'type' => QuestionTypeEnum::NUMERICAL_QUESTION,
            ],
        ];
    }
}
