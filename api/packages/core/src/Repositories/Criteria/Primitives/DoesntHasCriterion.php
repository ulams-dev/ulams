<?php


namespace Ulams\Core\Repositories\Criteria\Primitives;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class DoesntHasCriterion extends Criterion
{
    public function apply(Builder $query): Builder
    {
        return $query->whereDoesntHave($this->key, $this->value);
    }
}
