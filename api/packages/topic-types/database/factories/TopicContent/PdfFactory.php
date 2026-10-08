<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent;

use EscolaLms\TopicTypes\Database\Factories\TopicContent\Components\FileHelper;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdfFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PDF::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'value' => '1.pdf',
        ];
    }

    public function updatePath(int $pdfId)
    {
        return $this->state(function () use ($pdfId) {
            return FileHelper::uploadFile($pdfId, $this->faker->word, 'pdf');
        });
    }
}
