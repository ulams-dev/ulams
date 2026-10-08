<?php

namespace Ulams\Video\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface VideoRepositoryContract extends BaseRepositoryContract
{
    public function getByProcessDateBefore(Carbon $dateTime, string $state): Collection;
}
