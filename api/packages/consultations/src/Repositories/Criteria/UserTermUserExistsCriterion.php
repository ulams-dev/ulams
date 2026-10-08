<?php

namespace EscolaLms\Consultations\Repositories\Criteria;

use EscolaLms\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class UserTermUserExistsCriterion extends Criterion
{
    public function __construct(array $value = null)
    {
        parent::__construct(null, $value);
    }

    public function apply(Builder $query): Builder
    {
        return $query->whereHas('consultationUser', fn ($query) => $query->whereHas('user'));
    }
}
