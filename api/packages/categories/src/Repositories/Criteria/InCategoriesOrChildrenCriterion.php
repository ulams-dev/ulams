<?php

namespace EscolaLms\Categories\Repositories\Criteria;

use EscolaLms\Categories\Services\Contracts\CategoryServiceContracts;
use EscolaLms\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class InCategoriesOrChildrenCriterion extends Criterion
{
    public function apply(Builder $query): Builder
    {
        $ids = app(CategoryServiceContracts::class)->allCategoriesAndChildrenIds($this->value);
        return $query->whereHas(
            'categories',
            fn (Builder $query) => $query->whereIn('categories.id', $ids),
        );
    }
}
