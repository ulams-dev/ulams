<?php

namespace EscolaLms\TopicTypeProject\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use EscolaLms\TopicTypeProject\Models\Project;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'value' => $this->faker->text(),
            'counts_to_grade' => $this->faker->boolean(),
            'weight' => $this->faker->numberBetween(1, 10),
            'max_score' => $this->faker->numberBetween(1, 100),
        ];
    }
}
