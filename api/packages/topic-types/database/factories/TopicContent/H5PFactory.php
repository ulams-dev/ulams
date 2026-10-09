<?php

namespace Ulams\TopicTypes\Database\Factories\TopicContent;

use Ulams\H5P\Models\H5PContent;
use Ulams\TopicTypes\Database\Factories\TopicContent\Components\H5PHelper;
use Ulams\TopicTypes\Models\TopicContent\H5P;
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
        $h5p = H5PContent::query()->inRandomOrder()->first();

        return [
            'value' => isset($h5p) ? $h5p->id : H5PHelper::createH5PContent()->id,
        ];
    }
}
