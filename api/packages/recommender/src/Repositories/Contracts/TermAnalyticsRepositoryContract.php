<?php

namespace Ulams\Recommender\Repositories\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Recommender\Dto\TermAnalyticsFilterListDto;
use Ulams\Recommender\Models\TermAnalytic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TermAnalyticsRepositoryContract
{
    public function findByCriteria(
        string $modelType,
        TermAnalyticsFilterListDto $criteriaDto,
        int $perPage,
        ?OrderDto $orderDto = null
    ): LengthAwarePaginator;
    public function findById(string $modelType, int $id): TermAnalytic;
}
