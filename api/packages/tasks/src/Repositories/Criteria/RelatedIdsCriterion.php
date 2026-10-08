<?php

namespace EscolaLms\Tasks\Repositories\Criteria;

use EscolaLms\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class RelatedIdsCriterion extends Criterion
{
    public function __construct($value)
    {
        parent::__construct(null, $value);
    }

    public function apply(Builder $query): Builder
    {
        collect($this->value)
            ->each(fn($item, $key) => $query
                ->orWhere(fn(Builder $q) => $q
                    ->where('related_type', $key)
                    ->whereIn('related_id', $item)
                )
            );

        return $query;
    }
}

