<?php

namespace EscolaLms\Recommender\Database\Factories;

use EscolaLms\Recommender\Models\AggregatedFrame;
use Illuminate\Database\Eloquent\Factories\Factory;

class AggregatedFrameFactory extends Factory
{
    protected $model = AggregatedFrame::class;

    public function definition()
    {
        return [
            'external_id' => $this->faker->randomNumber(),
            'window_start' => $this->faker->dateTime(),
            'window_end' => $this->faker->dateTime(),
            'count' => $this->faker->numberBetween(1, 100),
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
            'median_attention' => $this->faker->randomFloat(25, 0, 1),
            'median_emotions_angry' => $this->faker->randomFloat(25, 0, 1),
            'median_emotions_disgusted' => $this->faker->randomFloat(25, 0, 1),
            'median_emotions_fearful' => $this->faker->randomFloat(25, 0, 1),
            'median_emotions_happy' => $this->faker->randomFloat(25, 0, 1),
            'median_emotions_neutral' => $this->faker->randomFloat(25, 0, 1),
            'median_emotions_sad' => $this->faker->randomFloat(25, 0, 1),
            'median_emotions_surprised' => $this->faker->randomFloat(25, 0, 1),
            'send_at' => $this->faker->dateTime(),
            'aggregated_at' => $this->faker->dateTime(),
            'model_type' => $this->faker->randomElement(['consultation', 'webinar']),
            'model_id' => $this->faker->randomNumber(),
            'term' => $this->faker->dateTime(),
        ];
    }
}
