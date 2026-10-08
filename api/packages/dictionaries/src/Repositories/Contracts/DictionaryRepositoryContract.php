<?php

namespace EscolaLms\Dictionaries\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DictionaryRepositoryContract extends BaseRepositoryContract
{
    public function findAll(array $criteria, int $perPage, string $orderDirection, string $orderColumn): LengthAwarePaginator;
}
