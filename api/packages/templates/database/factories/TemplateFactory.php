<?php

namespace Ulams\Templates\Database\Factories;

use Ulams\Templates\Contracts\TemplateChannelContract;
use Ulams\Templates\Contracts\TemplateVariableContract;
use Ulams\Templates\Models\Template;
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
