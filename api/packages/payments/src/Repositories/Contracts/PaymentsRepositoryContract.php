<?php

namespace EscolaLms\Payments\Repositories\Contracts;

use EscolaLms\Core\Dtos\CriteriaDto;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Builder;

interface PaymentsRepositoryContract extends BaseRepositoryContract
{
    public function searchAndOrder(?CriteriaDto $criteriaDto, ?OrderDto $orderDto): Builder;
    public function applyOrderDto(Builder $query, OrderDto $dto): Builder;
    public function applyCriteriaDto(Builder $query, CriteriaDto $dto): Builder;
}
