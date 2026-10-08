<?php

namespace Ulams\Questionnaire\Repository\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Questionnaire\Models\Questionnaire;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface QuestionnaireRepositoryContract extends BaseRepositoryContract
{
    public function findActive(int $id): Questionnaire;
    public function searchAndPaginate(array $search = [], ?int $perPage = null, string $orderDirection = 'asc', string $orderColumn = 'id'): LengthAwarePaginator;
    public function deleteQuestionnaire(int $id): bool;
    public function searchByCriteriaAndPaginate(array $criteria, array $with = []): LengthAwarePaginator;
}
