<?php

namespace Ulams\Courses\Tests\Database\Factories;

use Ulams\Courses\Tests\Models\TopicContent\SecondExampleTopicType;
use Illuminate\Database\Eloquent\Factories\Factory;

class SecondExampleTopicTypeFactory extends Factory
{
    protected $model = SecondExampleTopicType::class;

    public function definition()
    {
        $this->faker->addProvider(new \DavidBadura\FakerMarkdownGenerator\FakerProvider($this->faker));

        return [
            //'topic_id' => $this->faker->word,
            'value' => $this->faker->text(200),
        ];
    }
}
