<?php

namespace Ulams\Consultations\Repositories\Criteria;

use Ulams\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;

class ConsultationTermEqualCriterion extends Criterion
{
    public function __construct(int $value = null)
    {
        parent::__construct(null, $value);
    }

    public function apply(Builder $query): Builder
    {
        return $query->whereHas(
            'terms',
            fn (Builder $query) => $query->where('consultation_user.id', '=', $this->value)
        );
    }
}
