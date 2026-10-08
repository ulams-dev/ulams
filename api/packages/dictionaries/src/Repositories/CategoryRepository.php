<?php

namespace EscolaLms\Dictionaries\Repositories;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Core\Repositories\Criteria\Criterion;
use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Repositories\Contracts\CategoryRepositoryContract;
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
            ->get();
    }
}
