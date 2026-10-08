<?php

namespace Ulams\Dictionaries\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Collection;

interface CategoryRepositoryContract extends BaseRepositoryContract
{
    public function getCategoriesFilteredByDictionaryWord(array $dictionaryWordCriteria): Collection;
}
