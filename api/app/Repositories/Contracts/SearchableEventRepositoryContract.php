<?php

namespace App\Repositories\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Illuminate\Database\Query\Builder;

interface SearchableEventRepositoryContract
{
    public function eventsQuery(OrderDto $orderDto): Builder;
}
