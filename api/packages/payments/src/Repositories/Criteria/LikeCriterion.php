<?php

namespace EscolaLms\Payments\Repositories\Criteria;

use EscolaLms\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class LikeCriterion extends Criterion
{
    public function __construct(?string $key = null, $value = null)
    {
        parent::__construct($key, $value, 'LIKE');
    }

    public function apply(Builder $query): Builder
    {
        return $query->where($this->key, $this->operator, '%' . $this->value . '%');
    }
}
