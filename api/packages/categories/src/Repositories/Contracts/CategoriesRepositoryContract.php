<?php

namespace EscolaLms\Categories\Repositories\Contracts;

use EscolaLms\Categories\Dtos\CategoryCriteriaFilterDto;
use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Repositories\Contracts\ActivationContract;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoriesRepositoryContract extends BaseRepositoryContract, ActivationContract
{
    public function allRoots(array $search = [], ?int $skip = null, ?int $limit = null);
    public function listAll(CategoryCriteriaFilterDto $criteriaDto, OrderDto $dto, array $columns = ['*'], ?int $perPage = 15, ?bool $isActive): LengthAwarePaginator;
    public function get(int $id): Category;
}
