<?php

namespace EscolaLms\Dictionaries\Database\Factories;

use EscolaLms\Dictionaries\Models\Dictionary;
use Illuminate\Database\Eloquent\Factories\Factory;

class DictionaryFactory extends Factory
{
    protected $model = Dictionary::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'slug' => $this->faker->unique()->slug,
            'free_views_count' => $this->faker->numberBetween(1),
        ];
    }
}
