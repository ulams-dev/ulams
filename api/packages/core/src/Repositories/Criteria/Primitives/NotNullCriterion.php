<?php

namespace Ulams\Core\Repositories\Criteria\Primitives;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class NotNullCriterion extends Criterion
{
    public function apply(Builder $query): Builder
    {
        return $query->whereNotNull($this->key);
    }
}
