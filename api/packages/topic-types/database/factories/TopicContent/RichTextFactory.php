<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent;

use DavidBadura\FakerMarkdownGenerator\FakerProvider;
use EscolaLms\TopicTypes\Database\Factories\TopicContent\Enums\TextHelperEnum;
use EscolaLms\TopicTypes\Models\TopicContent\RichText;
use Illuminate\Database\Eloquent\Factories\Factory;

if (!function_exists('getMDSink')) {
    function getMDSink()
    {
        return TextHelperEnum::MD_SINK_PHRASE;
    }
}

class RichTextFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RichText::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $this->faker->addProvider(new FakerProvider($this->faker));
        return [
            //'topic_id' => $this->faker->word,
            'value' => $this->faker->markdown,
        ];
    }
}
