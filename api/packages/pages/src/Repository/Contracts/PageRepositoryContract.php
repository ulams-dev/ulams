<?php

namespace EscolaLms\Pages\Repository\Contracts;

use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use EscolaLms\Pages\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PageRepositoryContract extends BaseRepositoryContract
{
    public function searchAndPaginate(array $search = [], ?int $perPage = null, string $orderDirection = 'asc', string $orderColumn = 'id'): LengthAwarePaginator;
    public function getBySlug(string $slug): Page;
    public function deletePage(int $id): bool;
}
