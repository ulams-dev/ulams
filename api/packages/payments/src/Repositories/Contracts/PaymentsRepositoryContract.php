<?php

namespace Ulams\Payments\Repositories\Contracts;

use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Builder;

interface PaymentsRepositoryContract extends BaseRepositoryContract
{
    public function searchAndOrder(?CriteriaDto $criteriaDto, ?OrderDto $orderDto): Builder;
    public function applyOrderDto(Builder $query, OrderDto $dto): Builder;
    public function applyCriteriaDto(Builder $query, CriteriaDto $dto): Builder;
}
