<?php

namespace EscolaLms\Recommender\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MeetRecordingScreenFactory extends Factory
{
    protected $model = MeetRecordingScreenFactory::class;

    public function definition()
    {
        return [
            'model_type' => $this->faker->randomElement(['consultation', 'webinar']),
            'model_id' => $this->faker->randomNumber(),
            'term' => $this->faker->dateTime(),
            'file_path' => $this->faker->url(),
            'file_timestamp' => $this->faker->dateTime(),
        ];
    }
}
