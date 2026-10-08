<?php

namespace EscolaLms\Dictionaries\Repositories\Criteria;

use EscolaLms\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StartILikeCriterion extends Criterion
{
    public function apply(Builder $query): Builder
    {
        if (DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            return $query->where($this->key, 'ILIKE', "$this->value%");
        }

        return $query->where(DB::raw("lower($this->key)"), 'like',  strtolower($this->value) . '%');
    }
}
