<?php

namespace EscolaLms\StationaryEvents\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface StationaryEventRepositoryContract
{
    public function forCurrentUser(array $criteria = []): Builder;
}
