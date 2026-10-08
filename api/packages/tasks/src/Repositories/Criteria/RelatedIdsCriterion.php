<?php

namespace Ulams\Tasks\Repositories\Criteria;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class RelatedIdsCriterion extends Criterion
{
    public function __construct($value)
    {
        parent::__construct(null, $value);
    }

    public function apply(Builder $query): Builder
    {
        // The alternatives are grouped so the OR cannot escape other conditions (such as the user_id filter).
        return $query->where(function (Builder $query) {
            collect($this->value)
                ->each(fn($item, $key) => $query
                    ->orWhere(fn(Builder $q) => $q
                        ->where('related_type', $key)
                        ->whereIn('related_id', $item)
                    )
                );
        });
    }
}

