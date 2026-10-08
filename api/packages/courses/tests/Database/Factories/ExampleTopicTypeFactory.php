<?php

namespace Ulams\Courses\Tests\Database\Factories;

use Ulams\Courses\Tests\Models\TopicContent\ExampleTopicType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExampleTopicTypeFactory extends Factory
{
    protected $model = ExampleTopicType::class;

    public function definition()
    {
        $this->faker->addProvider(new \DavidBadura\FakerMarkdownGenerator\FakerProvider($this->faker));

        return [
            //'topic_id' => $this->faker->word,
            'value' => $this->faker->text(200),
        ];
    }
}
