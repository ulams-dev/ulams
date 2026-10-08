<?php

namespace Ulams\Questionnaire\Repository\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Questionnaire\Dtos\QuestionFilterCriteriaDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface QuestionRepositoryContract extends BaseRepositoryContract
{
    public function searchAndPaginate(array $search = [], ?int $perPage = null, string $orderDirection = 'asc', string $orderColumn = 'id'): LengthAwarePaginator;

    public function listWithCriteriaAndOrder(QuestionFilterCriteriaDto $criteriaDto, OrderDto $orderDto, int $perPage): LengthAwarePaginator;

    public function getAllQuestionnaireQuestions(int $id): Collection;
}
