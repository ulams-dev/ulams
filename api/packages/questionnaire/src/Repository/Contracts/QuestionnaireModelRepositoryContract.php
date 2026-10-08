<?php

namespace Ulams\Questionnaire\Repository\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Questionnaire\Models\Questionnaire;
use Ulams\Questionnaire\Models\QuestionnaireModel;

interface QuestionnaireModelRepositoryContract extends BaseRepositoryContract
{
    public function findByModelTitleAndModelId(string $title, int $model_id, int $questionnaireId): QuestionnaireModel;
    public function assignQuestionnaireModel(Questionnaire $questionnaire, int $modelTypeId, int $id): QuestionnaireModel;
    public function unassignQuestionnaireModel(Questionnaire $questionnaire, int $modelTypeId, int $modelId): bool;
    public function updateOrCreate(array $attributes, array $values = []): QuestionnaireModel;
}
