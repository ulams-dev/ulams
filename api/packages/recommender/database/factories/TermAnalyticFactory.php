<?php

namespace EscolaLms\Recommender\Database\Factories;

use EscolaLms\Recommender\Models\TermAnalytic;
use Illuminate\Database\Eloquent\Factories\Factory;

class TermAnalyticFactory extends Factory
{
    protected $model = TermAnalytic::class;

    public function definition()
    {
        return [
            'count' => $this->faker->numberBetween(20, 100),
            'aggregated_frames_count' => $this->faker->numberBetween(1, 20),
            'sum_attention' => $this->faker->randomFloat(25, 0, 1),
            'sum_emotions_angry' => $this->faker->randomFloat(25, 0, 1),
            'sum_emotions_disgusted' => $this->faker->randomFloat(25, 0, 1),
            'sum_emotions_fearful' => $this->faker->randomFloat(25, 0, 1),
            'sum_emotions_happy' => $this->faker->randomFloat(25, 0, 1),
            'sum_emotions_neutral' => $this->faker->randomFloat(25, 0, 1),
            'sum_emotions_sad' => $this->faker->randomFloat(25, 0, 1),
            'sum_emotions_surprised' => $this->faker->randomFloat(25, 0, 1),
            'avg_attention' => $this->faker->randomFloat(25, 0, 1),
            'avg_emotions_angry' => $this->faker->randomFloat(25, 0, 1),
            'avg_emotions_disgusted' => $this->faker->randomFloat(25, 0, 1),
            'avg_emotions_fearful' => $this->faker->randomFloat(25, 0, 1),
            'avg_emotions_happy' => $this->faker->randomFloat(25, 0, 1),
            'avg_emotions_neutral' => $this->faker->randomFloat(25, 0, 1),
            'avg_emotions_sad' => $this->faker->randomFloat(25, 0, 1),
            'avg_emotions_surprised' => $this->faker->randomFloat(25, 0, 1),
            'model_type' => $this->faker->randomElement(['consultation', 'webinar']),
            'model_id' => $this->faker->randomNumber(),
            'term' => $this->faker->dateTime(),
            'last_frame_at' => $this->faker->dateTime(),
        ];
    }
}
