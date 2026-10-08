<?php

namespace EscolaLms\Dictionaries\Database\Factories;

use EscolaLms\Dictionaries\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'description' => $this->faker->sentence,
            'slug' => $this->faker->slug,
        ];
    }
}
