<?php

namespace Ulams\Dictionaries\Database\Factories;

use Ulams\Dictionaries\Models\Dictionary;
use Ulams\Dictionaries\Models\DictionaryWord;
use Illuminate\Database\Eloquent\Factories\Factory;

class DictionaryWordFactory extends Factory
{
    protected $model = DictionaryWord::class;

    public function definition(): array
    {
        return [
            'dictionary_id' => Dictionary::factory(),
            'word' => $this->faker->word,
            'description' => $this->faker->text,
            'data' => [],
        ];
    }
}
