<?php

namespace Ulams\Lrs\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\Lrs\Models\Statement;
use Ulams\Lrs\Repositories\Contracts\StatementRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StatementRepository extends BaseRepository implements StatementRepositoryContract
{
    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return Statement::class;
    }

    public function searchAndPaginateByCriteria(array $criteria, ?int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();
        $query = $this->applyCriteria($query, $criteria);

        return $query
            ->paginate($perPage);
    }
}
