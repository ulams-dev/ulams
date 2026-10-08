<?php

namespace EscolaLms\BulkNotifications\Repositories;

use EscolaLms\BulkNotifications\Models\BulkNotification;
use EscolaLms\BulkNotifications\Repositories\Contracts\BulkNotificationRepositoryContract;
use EscolaLms\Core\Repositories\BaseRepository;
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
