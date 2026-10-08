<?php

namespace App\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Illuminate\Database\Query\Builder;

interface SearchableEventServiceContract
{
    public function getEventsList(OrderDto $orderDto): Builder;
}
