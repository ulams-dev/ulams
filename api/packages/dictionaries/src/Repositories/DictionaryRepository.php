<?php

namespace EscolaLms\Dictionaries\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Dictionaries\Models\Dictionary;
use EscolaLms\Dictionaries\Repositories\Contracts\DictionaryRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DictionaryRepository extends BaseRepository implements DictionaryRepositoryContract
{
    public function model(): string
    {
        return Dictionary::class;
    }

    public function getFieldsSearchable(): array
    {
        return [
            'title',
            'slug',
        ];
    }

    public function findAll(array $criteria, int $perPage, string $orderDirection, string $orderColumn): LengthAwarePaginator
    {
        return $this->queryWithAppliedCriteria($criteria)
            ->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage);
    }
}
