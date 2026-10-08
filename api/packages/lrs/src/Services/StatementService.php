<?php

namespace Ulams\Lrs\Services;

use Ulams\Lrs\Dto\StatementSearchDto;
use Ulams\Lrs\Repositories\Contracts\StatementRepositoryContract;
use Ulams\Lrs\Services\Contracts\StatementServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StatementService implements StatementServiceContract
{
    private StatementRepositoryContract $statementRepository;

    public function __construct(StatementRepositoryContract $statementRepository)
    {
        $this->statementRepository = $statementRepository;
    }

    public function searchAndPaginate(StatementSearchDto $criteria, int $per_page): LengthAwarePaginator
    {
        return $this->statementRepository->searchAndPaginateByCriteria($criteria->toArray(), $per_page);
    }
}
