<?php

namespace EscolaLms\Reports\Metrics;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Questionnaire\Models\Question;
use EscolaLms\Questionnaire\Models\QuestionAnswer;
use EscolaLms\Questionnaire\Models\Questionnaire;
use EscolaLms\Questionnaire\Models\QuestionnaireModel;
use EscolaLms\Questionnaire\Models\QuestionnaireModelType;
use Illuminate\Support\Collection;

class CoursesBestRatedMetric extends AbstractCoursesMetric
{
    public function calculate(?int $limit = null): Collection
    {
        $questionAnswerTable = (new QuestionAnswer())->getTable();
        $questionnaireModelTable = (new QuestionnaireModel())->getTable();
        $questionnaireModelTypeTable = (new QuestionnaireModelType())->getTable();
        $questionTable = (new Question())->getTable();
        $courseTable = (new Course())->getTable();

        return Question::query()
            ->selectRaw($courseTable . ".title as label, " . $questionnaireModelTable . '.model_id as id, ' .' SUM(rate) as value')
            ->join($questionAnswerTable, $questionAnswerTable . '.question_id', '=', $questionTable . '.id')
            ->join($questionnaireModelTable, $questionAnswerTable . '.questionnaire_model_id', '=', $questionnaireModelTable . '.id')
            ->join($questionnaireModelTypeTable, $questionnaireModelTable . '.model_type_id', '=', $questionnaireModelTypeTable . '.id')
            ->join($courseTable, $questionnaireModelTable . '.model_id', '=', $courseTable . '.id',)
            ->groupBy($questionnaireModelTable . '.model_id', $courseTable . '.title')
            ->orderBy('value', 'DESC')
            ->take($this->getLimit($limit))
            ->get(['id', 'label', 'value']);
    }

    public function requiredPackage(): string
    {
        return 'escolalms/courses & escolalms/questionnaire';
    }

    public static function requiredPackageInstalled(): bool
    {
        return class_exists(Course::class) &&
            class_exists(Questionnaire::class) &&
            class_exists(Question::class);
    }
}
