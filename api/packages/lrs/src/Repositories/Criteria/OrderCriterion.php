<?php

namespace Ulams\Lrs\Repositories\Criteria;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class OrderCriterion extends Criterion
{
    public function apply(Builder $query): Builder
    {
        return $query->orderBy($this->key, $this->value);
    }
}
