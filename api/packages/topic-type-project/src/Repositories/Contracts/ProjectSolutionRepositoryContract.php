<?php

namespace Ulams\TopicTypeProject\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjectSolutionRepositoryContract extends BaseRepositoryContract
{
    public function findById(int $id): ProjectSolution;

    public function findByCriteria(array $criteria, int $perPage): LengthAwarePaginator;
}
