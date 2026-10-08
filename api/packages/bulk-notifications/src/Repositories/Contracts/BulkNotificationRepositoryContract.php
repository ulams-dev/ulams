<?php

namespace EscolaLms\BulkNotifications\Repositories\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BulkNotificationRepositoryContract extends BaseRepositoryContract
{
    public function findAll(array $criteria, int $perPage, string $orderDirection, string $orderColumn): LengthAwarePaginator;
}
