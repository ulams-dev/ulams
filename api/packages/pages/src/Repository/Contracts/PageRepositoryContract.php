<?php

namespace Ulams\Pages\Repository\Contracts;

use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Pages\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PageRepositoryContract extends BaseRepositoryContract
{
    public function searchAndPaginate(array $search = [], ?int $perPage = null, string $orderDirection = 'asc', string $orderColumn = 'id'): LengthAwarePaginator;
    public function getBySlug(string $slug): Page;
    public function deletePage(int $id): bool;
}
