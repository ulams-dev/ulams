<?php

namespace EscolaLms\Consultations\Repositories\Criteria;

use EscolaLms\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class CategoriesCriterion extends Criterion
{
    public function __construct(array $value = null)
    {
        parent::__construct(null, $value);
    }

    public function apply(Builder $query): Builder
    {
        return $query->whereHas(
            'categories',
            fn (Builder $query) => $query->whereIn('categories.id', $this->value)
        );
    }
}
