<?php

namespace Ulams\Auth\Repositories\Criteria\AdditionalField;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class AdditionalFieldBooleanCriterion extends Criterion
{
    public function apply(Builder $query): Builder
    {
        return $query->whereHas('fields', function ($query) {
            return $query->where([
                ['name', '=', $this->key],
                ['value', '=', $this->value ? 'true' : ''],
            ]);
        });
    }
}
