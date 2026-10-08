<?php

namespace EscolaLms\TopicTypeGift\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;

class GiftQuizFactory extends Factory
{
    protected $model = GiftQuiz::class;

    public function definition(): array
    {
        return [
            'value' => $this->faker->text,
            'max_attempts' => $this->faker->numberBetween(1, 10),
            'max_execution_time' => $this->faker->numberBetween(1, 20),
            'min_pass_score' => $this->faker->numberBetween(1, 10),
            'counts_to_grade' => $this->faker->boolean(),
            'weight' => $this->faker->numberBetween(1, 10),
            'randomize_order' => $this->faker->boolean(),
        ];
    }
}
