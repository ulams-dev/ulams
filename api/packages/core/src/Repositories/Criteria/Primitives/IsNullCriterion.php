<?php


namespace Ulams\Core\Repositories\Criteria\Primitives;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class IsNullCriterion extends Criterion
{
    public function apply(Builder $query): Builder
    {
        return $query->whereNull($this->key);
    }
}
