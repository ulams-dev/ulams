<?php

namespace EscolaLms\Video\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface VideoRepositoryContract extends BaseRepositoryContract
{
    public function getByProcessDateBefore(Carbon $dateTime, string $state): Collection;
}
