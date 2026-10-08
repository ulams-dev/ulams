<?php

namespace Ulams\H5P\Services\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Ulams\H5P\Dtos\H5PContentCriteriaDto;

interface H5PContentServiceContract
{
    /**
     * @return LengthAwarePaginator|Collection Collection when $perPage is 0 (all rows)
     */
    public function list(H5PContentCriteriaDto $criteria, int $perPage, string $orderBy = 'id', string $order = 'desc');

    /**
     * Deletes (through the H5P service) every content no H5P topic references.
     *
     * @return array{ids: int[], failed: array<int, array{id: int, message: string}>}
     */
    public function deleteUnused(): array;
}
