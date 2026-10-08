<?php

namespace EscolaLms\StationaryEvents\Database\Factories;

use EscolaLms\StationaryEvents\Enum\StationaryEventStatusEnum;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class StationaryEventFactory extends Factory
{
    protected $model = StationaryEvent::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+2 month', '+6 month');

        return [
            'name' => $this->faker->sentence(5),
            'status' => $this->faker->randomElement(StationaryEventStatusEnum::getValues()),
            'description' => $this->faker->sentence,
            'started_at' => $startDate->format('Y-m-d H:i:s'),
            'finished_at' => (clone $startDate)->modify('+' . random_int(1, 5) . ' hour')->format('Y-m-d H:i:s'),
            'place' => $this->faker->city,
            'max_participants' => $this->faker->numberBetween(1, 200),
            'short_desc' => $this->faker->sentence(10),
        ];
    }
}
