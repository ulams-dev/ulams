<?php

namespace Ulams\Reports\Tests\Traits;

use Ulams\Courses\Models\Course;
use Ulams\Questionnaire\Models\QuestionnaireModelType;

trait QuestionnaireTestingTrait
{
    public function getCourseQuestionnaireModelType(): QuestionnaireModelType {
        $modelType = QuestionnaireModelType::query()
            ->where('title', '=', 'course')
            ->first();

        if (!$modelType) {
            $modelType = QuestionnaireModelType::factory()
                ->create([
                    'title' => 'course',
                    'model_class' => Course::class
                ]);
            $modelType->save();
        }

        return $modelType;
    }
}
