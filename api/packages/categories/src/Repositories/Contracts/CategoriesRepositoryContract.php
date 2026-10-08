<?php

namespace Ulams\Categories\Repositories\Contracts;

use Ulams\Categories\Dtos\CategoryCriteriaFilterDto;
use Ulams\Categories\Models\Category;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Contracts\ActivationContract;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoriesRepositoryContract extends BaseRepositoryContract, ActivationContract
{
    public function allRoots(array $search = [], ?int $skip = null, ?int $limit = null);
    public function listAll(CategoryCriteriaFilterDto $criteriaDto, OrderDto $dto, array $columns = ['*'], ?int $perPage = 15, ?bool $isActive): LengthAwarePaginator;
    public function get(int $id): Category;
}
