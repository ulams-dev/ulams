<?php

namespace EscolaLms\Tasks\Repositories;

use Illuminate\Support\Carbon;
use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Repositories\Contracts\TaskRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskRepository extends BaseRepository implements TaskRepositoryContract
{
    public function model(): string
    {
        return Task::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function findAllCompletedByDueDate(array $dueDates, ?bool $completed = true): Collection
    {
        return $this->model->newQuery()
            ->whereBetween('due_date', $dueDates)
            ->whereNull('completed_at', 'and', $completed)
            ->get();
    }

    public function findAllByUserId(int $userId, int $perPage, array $criteria, string $orderDirection, string $orderColumn): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with(['taskNotes', 'user', 'createdBy']);
        $query = $this->applyCriteria($query, $criteria);
        $query = $query->where('user_id', '=', $userId);

        return $query
            ->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage);
    }

    public function findAll(int $perPage, array $criteria, string $orderDirection, string $orderColumn): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with(['taskNotes', 'user', 'createdBy']);
        $query = $this->applyCriteria($query, $criteria);

        return $query
            ->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage);
    }
}
