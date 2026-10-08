<?php

namespace EscolaLms\Recommender\Repositories\Criteria;

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
        return $query
            ->join('category_consultation as cc', 'cc.consultation_id', '=', 'ta.model_id')
            ->where('ta.model_type', '=', 'consultation')
            ->whereIn('cc.category_id', $this->value);
    }
}
