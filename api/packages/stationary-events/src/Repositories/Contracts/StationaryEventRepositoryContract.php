<?php

namespace Ulams\StationaryEvents\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface StationaryEventRepositoryContract
{
    public function forCurrentUser(array $criteria = []): Builder;
}
