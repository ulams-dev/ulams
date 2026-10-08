<?php

namespace EscolaLms\Templates\Database\Factories;

use EscolaLms\Templates\Contracts\TemplateChannelContract;
use EscolaLms\Templates\Contracts\TemplateVariableContract;
use EscolaLms\Templates\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition()
    {
        $title = $this->faker->catchPhrase;
        return [
            'name' => Str::slug($title, '-'),
            'channel' => TemplateChannelContract::class,
            'event' => TemplateVariableContract::class,
            'default' => false,
        ];
    }
}
