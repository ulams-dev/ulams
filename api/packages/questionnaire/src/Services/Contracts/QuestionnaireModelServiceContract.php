<?php

namespace Ulams\Questionnaire\Services\Contracts;

use Ulams\Questionnaire\Dtos\QuestionnaireModelDto;
use Ulams\Questionnaire\Models\QuestionnaireModel;
use Ulams\Questionnaire\Models\QuestionnaireModelType;
use Illuminate\Support\Collection;

/**
 * Interface QuestionnaireModelServiceContract
 * @package Ulams\Questionnaire\Http\Services\Contracts
 */
interface QuestionnaireModelServiceContract
{
    public function deleteQuestionnaireModel(QuestionnaireModel $questionnaireModel): bool;
    public function saveModelsForQuestionnaire(int $questionnaireId, array $models): void;
    public function assign(QuestionnaireModelType $questionnaireModelType, QuestionnaireModelDto $dto): QuestionnaireModel;
    public function unassign(QuestionnaireModelType $questionnaireModelType, QuestionnaireModelDto $dto): void;
    public function getQuestionnaireDataToExport(QuestionnaireModelDto $dto, QuestionnaireModelType $questionnaireModelType): Collection;
}
