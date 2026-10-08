<?php

namespace Ulams\Questionnaire\Database\Factories;

use Ulams\Questionnaire\Models\QuestionnaireModelType;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireModelTypeFactory extends Factory
{
    protected $model = QuestionnaireModelType::class;

    public function definition()
    {
        return [
            'title' => 'course',
            'model_class' => 'Ulams\Courses\Models\Course',
        ];
    }
}
