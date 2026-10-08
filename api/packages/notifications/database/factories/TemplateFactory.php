<?php

namespace EscolaLms\Notifications\Database\Factories;

use EscolaLms\Notifications\Models\Template;
use EscolaLms\Templates\Database\Factories\TemplateFactory as BaseTemplateFactory;

class TemplateFactory extends BaseTemplateFactory
{
    protected $model = Template::class;

    public function definition()
    {
        return array_merge(parent::definition(), [
            'is_default' => false,
            'title' => 'Template title',
        ]);
    }
}
