<?php

namespace Ulams\TopicTypeLayout\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ulams\TopicTypeLayout\Models\LayoutTopic;

class LayoutTopicFactory extends Factory
{
    protected $model = LayoutTopic::class;

    public function definition(): array
    {
        return [
            'document' => [
                ['component' => 'Callout', 'props' => ['tone' => 'tip', 'title' => 'Start here', 'text' => $this->faker->sentence()]],
            ],
            'schema_version' => LayoutTopic::SCHEMA_VERSION,
            'markdown_fallback' => '**Start here.** ' . $this->faker->sentence(),
        ];
    }
}
