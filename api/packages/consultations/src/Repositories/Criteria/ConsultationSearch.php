<?php

namespace EscolaLms\Consultations\Repositories\Criteria;

use EscolaLms\Core\Repositories\Criteria\Criterion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ConsultationSearch extends Criterion
{
    public function __construct($value = null)
    {
        parent::__construct(null, $value);
    }

    public function apply(Builder $query): Builder
    {
        $like = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'ILIKE' : 'LIKE';
        return $query->where('consultations.name', $like, '%' . $this->value . '%');
    }
}
