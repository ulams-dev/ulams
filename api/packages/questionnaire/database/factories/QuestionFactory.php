<?php

namespace Ulams\Questionnaire\Database\Factories;

use Ulams\Questionnaire\Enums\QuestionTypeEnum;
use Ulams\Questionnaire\Models\Question;
use Ulams\Questionnaire\Models\Questionnaire;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition()
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = Questionnaire::query()->inRandomOrder()->first();
        if (empty($questionnaire)) {
            Questionnaire::factory()
                ->count(1)
                ->create();
            $questionnaire = Questionnaire::query()->inRandomOrder()->first();
        }

        return [
            'description' => $this->faker->realText,
            'title' => $this->faker->realText . ' ?',
            'questionnaire_id' => $questionnaire->id,
            'position' => $this->faker->randomNumber(),
            'active' => true,
            'type' => QuestionTypeEnum::RATE,
        ];
    }
}
