<?php

namespace Ulams\Categories\Repositories\Criteria;

use Ulams\Categories\Services\Contracts\CategoryServiceContracts;
use Ulams\Core\Repositories\Criteria\Criterion;
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
