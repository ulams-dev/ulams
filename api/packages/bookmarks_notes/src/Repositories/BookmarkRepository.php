<?php

namespace EscolaLms\Bookmarks\Repositories;

use EscolaLms\Bookmarks\Models\Bookmark;
use EscolaLms\Bookmarks\Repositories\Contracts\BookmarkRepositoryContract;
use EscolaLms\Core\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookmarkRepository extends BaseRepository implements BookmarkRepositoryContract
{
    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function model(): string
    {
        return Bookmark::class;
    }

    public function findAll(?array $criteria = [], ?int $perPage = 15, ?string $orderDirection = 'desc', ?string $orderColumn = 'id'): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with(['user']);
        $query = $this->applyCriteria($query, $criteria);

        return $query
            ->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage);
    }

    public function findAllUser(int $userId, ?array $criteria = [], ?int $perPage = 15, ?string $orderDirection = 'desc', ?string $orderColumn = 'id'): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with(['user']);
        $query = $this->applyCriteria($query, $criteria);

        return $query
            ->where('user_id', '=', $userId)
            ->orderBy($orderColumn, $orderDirection)
            ->paginate($perPage);
    }
}
