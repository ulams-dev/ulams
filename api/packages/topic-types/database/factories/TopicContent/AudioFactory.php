<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent;

use EscolaLms\TopicTypes\Database\Factories\TopicContent\Components\FileHelper;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use Illuminate\Database\Eloquent\Factories\Factory;

class AudioFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Audio::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            //'topic_id' => $this->faker->word,
            'value' => '1.mp3',
            'length' => rand(1000, 2000),
        ];
    }

    public function updatePath(int $audioId)
    {
        return $this->state(function () use ($audioId) {
            return FileHelper::uploadFile($audioId, $this->faker->word, 'mp3');
        });
    }
}
