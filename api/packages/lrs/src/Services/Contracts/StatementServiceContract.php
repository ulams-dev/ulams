<?php

namespace Ulams\Lrs\Services\Contracts;

use Ulams\Lrs\Dto\StatementSearchDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StatementServiceContract
{
    public function searchAndPaginate(StatementSearchDto $criteria, int $per_page): LengthAwarePaginator;
}
