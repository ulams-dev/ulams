<?php

namespace Ulams\Bookmarks\Repositories\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookmarkRepositoryContract extends BaseRepositoryContract
{
    public function findAll(?array $criteria = [], ?int $perPage = 15, ?string $orderDirection = 'desc', ?string $orderColumn = 'id'): LengthAwarePaginator;

    public function findAllUser(int $userId, ?array $criteria = [], ?int $perPage = 15, ?string $orderDirection = 'desc', ?string $orderColumn = 'id'): LengthAwarePaginator;
}
