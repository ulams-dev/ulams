<?php

namespace Ulams\Dictionaries\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Dictionaries\Models\DictionaryWord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DictionaryWordRepositoryContract extends BaseRepositoryContract
{
    public function findAll(array $criteria, int $perPage, string $orderDirection, string $orderColumn): LengthAwarePaginator;
    public function findOrFail(int $id): DictionaryWord;
}
