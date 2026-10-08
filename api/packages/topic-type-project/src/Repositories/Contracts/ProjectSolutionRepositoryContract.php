<?php

namespace EscolaLms\TopicTypeProject\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjectSolutionRepositoryContract extends BaseRepositoryContract
{
    public function findById(int $id): ProjectSolution;

    public function findByCriteria(array $criteria, int $perPage): LengthAwarePaginator;
}
