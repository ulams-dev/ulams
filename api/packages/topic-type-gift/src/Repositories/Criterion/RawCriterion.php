<?php

namespace EscolaLms\TopicTypeGift\Repositories\Criterion;

use EscolaLms\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class RawCriterion extends Criterion
{
    private string $sql;
    private array $bindings;

    public function __construct(string $sql, array $bindings)
    {
        parent::__construct();

        $this->sql = $sql;
        $this->bindings = $bindings;
    }

    public function apply(Builder $query): Builder
    {
        return $query->whereRaw($this->sql, $this->bindings);
    }
}
