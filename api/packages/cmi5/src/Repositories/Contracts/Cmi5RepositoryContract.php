<?php

namespace EscolaLms\Cmi5\Repositories\Contracts;

use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Collection;

interface Cmi5RepositoryContract extends BaseRepositoryContract
{
    public function save(Cmi5 $cmi5, Collection $cmi5Aus): Cmi5;
}
