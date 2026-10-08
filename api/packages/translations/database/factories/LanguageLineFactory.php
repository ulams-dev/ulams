<?php

namespace EscolaLms\Translations\Database\Factories;

use EscolaLms\Translations\Models\LanguageLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class LanguageLineFactory extends Factory
{
    protected $model = LanguageLine::class;

    public function definition(): array
    {
        return [
            'group' => $this->faker->slug,
            'key' => $this->faker->slug,
            'text' => ['en' => $this->faker->words(5, true), 'pl' => $this->faker->words(5, true)],
            'public' => $this->faker->boolean
        ];
    }
}
