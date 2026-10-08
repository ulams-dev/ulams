<?php

namespace Ulams\Scorm\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Builder;

interface ScormRepositoryContract extends BaseRepositoryContract
{
    public function listQuery(?array $columns = ['*'], ?array $search = []): Builder;
}
