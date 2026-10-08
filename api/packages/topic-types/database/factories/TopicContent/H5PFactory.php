<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent;

use EscolaLms\HeadlessH5P\Models\H5PContent;
use EscolaLms\TopicTypes\Database\Factories\TopicContent\Components\H5PHelper;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use Illuminate\Database\Eloquent\Factories\Factory;

class H5PFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = H5P::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        if (!class_exists('EscolaLms\HeadlessH5P\Models\H5PContent')) {
            return [];
        }

        $h5p = H5PContent::inRandomOrder()->first();
        return [
            'value' => isset($h5p) ? $h5p->id : H5PHelper::createH5PContent()->id,
        ];
    }
}
