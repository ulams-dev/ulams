<?php

namespace Ulams\TemplatesPdf\Database\Factories;

use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\Templates\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ulams\Auth\Models\User;

class FabricPdfFactory extends Factory
{
    protected $model = FabricPDF::class;

    public function definition()
    {
        return [
            'user_id' => User::factory()->create()->id,
            'template_id' => Template::factory()->create()->id,
            'content' => json_encode(['foo' => 'bar'])
        ];
    }
}
