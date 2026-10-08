<?php

namespace Ulams\Webinar\Repositories\Criteria;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WebinarTagsCriterion extends Criterion
{
    public function __construct($value = null)
    {
        parent::__construct(null, $value);
    }

    public function apply(Builder $query): Builder
    {
        return $query->whereHas('tags', fn (Builder $query) =>
            $query->whereIn('title', $this->value)
        );
    }
}
