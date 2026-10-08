<?php


namespace Ulams\Core\Repositories\Criteria\Primitives;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class DateCriterion extends Criterion
{
    public function apply(Builder $query): Builder
    {
        return $query->whereDate($this->key, $this->operator, $this->value);
    }
}
