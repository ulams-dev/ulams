<?php

namespace EscolaLms\Dictionaries\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Collection;

interface CategoryRepositoryContract extends BaseRepositoryContract
{
    public function getCategoriesFilteredByDictionaryWord(array $dictionaryWordCriteria): Collection;
}
