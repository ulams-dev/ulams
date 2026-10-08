<?php

namespace Ulams\Auth\Repositories\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Builder;

interface UserGroupRepositoryContract extends BaseRepositoryContract
{
    public function orderBy(Builder $query, OrderDto $orderDto): Builder;
}
