<?php

namespace Ulams\Cmi5\Repositories\Contracts;

use Ulams\Cmi5\Models\Cmi5;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Collection;

interface Cmi5RepositoryContract extends BaseRepositoryContract
{
    public function save(Cmi5 $cmi5, Collection $cmi5Aus): Cmi5;
}
