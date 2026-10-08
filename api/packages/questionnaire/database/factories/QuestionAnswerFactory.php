<?php

namespace Ulams\Questionnaire\Database\Factories;

use Ulams\Core\Models\User;
use Ulams\Questionnaire\Models\Question;
use Ulams\Questionnaire\Models\QuestionAnswer;
use Ulams\Questionnaire\Models\QuestionnaireModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionAnswerFactory extends Factory
{
    protected $model = QuestionAnswer::class;

    public function definition()
    {
        /** @var Question $question */
        $question = Question::query()->inRandomOrder()->first();
        if (empty($question)) {
            Question::factory()
                ->count(1)
                ->create();
            $question = Question::query()->inRandomOrder()->first();
        }

        /** @var QuestionnaireModel $questionnaireModel */
        $questionnaireModel = QuestionnaireModel::query()->inRandomOrder()->first();
        if (empty($questionnaireModel)) {
            QuestionnaireModel::factory()
                ->count(1)
                ->create();
            $questionnaireModel = QuestionnaireModel::query()->inRandomOrder()->first();
        }

        /** @var User $user */
        $user = User::query()->inRandomOrder()->first();
        if (empty($user)) {
            User::factory()
                ->count(1)
                ->create();
            $user = User::query()->inRandomOrder()->first();
        }

        return [
            'questionnaire_model_id' => $questionnaireModel->id,
            'question_id' => $question->id,
            'user_id' => $user->id,
            'rate' => $this->faker->numberBetween(1, 5),
            'visible_on_front' => $this->faker->boolean,
        ];
    }
}
