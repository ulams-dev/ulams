<?php

namespace Ulams\Questionnaire\Repository;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\Questionnaire\Models\QuestionnaireModelType;
use Ulams\Questionnaire\Repository\Contracts\QuestionnaireModelTypeRepositoryContract;

class QuestionnaireModelTypeRepository extends BaseRepository implements QuestionnaireModelTypeRepositoryContract
{
    public function model(): string
    {
        return QuestionnaireModelType::class;
    }

    public function getFieldsSearchable(): array
    {
        return [
            'id',
            'title',
            'model_class',
        ];
    }
}
