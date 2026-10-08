<?php

namespace EscolaLms\Dictionaries\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use EscolaLms\Dictionaries\Repositories\Contracts\DictionaryWordRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DictionaryWordRepository extends BaseRepository implements DictionaryWordRepositoryContract
{
    public function model(): string
    {
        return DictionaryWord::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function findAll(array $criteria, int $perPage, string $orderDirection, string $orderColumn): LengthAwarePaginator
    {
        return $this->queryWithAppliedCriteria($criteria)
            ->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage);
    }

    public function findOrFail(int $id): DictionaryWord
    {
        /** @var DictionaryWord */
        return $this->model->newQuery()->findOrFail($id);
    }
}
