<?php

namespace Database\Factories\Ulams\Auth\Models;

use Ulams\Auth\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'parent_id' => null,
            'registerable' => $this->faker->boolean
        ];
    }
}
