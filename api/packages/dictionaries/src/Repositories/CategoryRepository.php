<?php

namespace Ulams\Dictionaries\Repositories;

use Ulams\Core\Repositories\BaseRepository;
use Ulams\Core\Repositories\Criteria\Criterion;
use Ulams\Dictionaries\Models\Category;
use Ulams\Dictionaries\Repositories\Contracts\CategoryRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryRepository extends BaseRepository implements CategoryRepositoryContract
{
    public function model(): string
    {
        return Category::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function getCategoriesFilteredByDictionaryWord(array $dictionaryWordCriteria): Collection
    {
        return $this->model->newQuery()
            ->whereRelation('dictionaryWords', fn(Builder $query) => $this
                ->applyCriteria($query, $dictionaryWordCriteria)
            )
            ->withCount([
                'dictionaryWords' => fn(Builder $query) => $this->applyCriteria($query, $dictionaryWordCriteria)
            ])
            ->orderBy($this->model->getQualifiedKeyName())
            ->get();
    }
}
