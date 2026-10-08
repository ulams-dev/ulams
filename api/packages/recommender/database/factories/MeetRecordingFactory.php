<?php

namespace EscolaLms\Recommender\Database\Factories;

use EscolaLms\Recommender\Enum\MeetRecordingEnum;
use EscolaLms\Recommender\Models\MeetRecording;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetRecordingFactory extends Factory
{
    protected $model = MeetRecording::class;

    public function definition()
    {
        return [
            'start_at' => $this->faker->dateTime(),
            'end_at' => $this->faker->dateTime(),
            'model_type' => $this->faker->randomElement(['consultation', 'webinar']),
            'model_id' => $this->faker->randomNumber(),
            'term' => $this->faker->dateTime(),
            'url' => $this->faker->url(),
            'url_expires_at' => $this->faker->dateTime(),
        ];
    }
}
