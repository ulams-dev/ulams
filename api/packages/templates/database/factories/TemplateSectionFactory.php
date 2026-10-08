<?php

namespace EscolaLms\Templates\Database\Factories;

use EscolaLms\Templates\Models\TemplateSection;
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
