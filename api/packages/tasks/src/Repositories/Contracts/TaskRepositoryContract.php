<?php

namespace EscolaLms\Tasks\Repositories\Contracts;

use Illuminate\Support\Carbon;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TaskRepositoryContract extends BaseRepositoryContract
{
    public function findAllCompletedByDueDate(array $dueDates, ?bool $completed = true): Collection;

    public function findAllByUserId(int $userId, int $perPage, array $criteria, string $orderDirection, string $orderColumn): LengthAwarePaginator;

    public function findAll(int $perPage, array $criteria, string $orderDirection, string $orderColumn): LengthAwarePaginator;
}
