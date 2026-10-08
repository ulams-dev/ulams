<?php

namespace Ulams\Templates\Database\Factories;

use Ulams\Templates\Models\TemplateSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class TemplateSectionFactory extends Factory
{
    protected $model = TemplateSection::class;

    public function definition()
    {
        return [
            'key' => $this->faker->word,
            'content' => $this->faker->text()
        ];
    }
}
