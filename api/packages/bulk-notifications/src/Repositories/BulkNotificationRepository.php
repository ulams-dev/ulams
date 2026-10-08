<?php

namespace Ulams\BulkNotifications\Repositories;

use Ulams\BulkNotifications\Models\BulkNotification;
use Ulams\BulkNotifications\Repositories\Contracts\BulkNotificationRepositoryContract;
use Ulams\Core\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BulkNotificationRepository extends BaseRepository implements BulkNotificationRepositoryContract
{

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return BulkNotification::class;
    }

    public function findAll(array $criteria, int $perPage, string $orderDirection, string $orderColumn): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with(['sections', 'users']);
        $query = $this->applyCriteria($query, $criteria);

        return $query
            ->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage);
    }
}
