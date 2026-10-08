<?php

namespace Ulams\Categories\Services\Contracts;

use Ulams\Categories\Dtos\CategoryDto;
use Ulams\Categories\Dtos\CategorySortDto;
use Ulams\Categories\Models\Category;

interface CategoryServiceContracts
{
    public function getList(?string $search = null);

    public function find(?int $id = null);

    public function store(CategoryDto $categoryDto): Category;

    public function update(int $id, CategoryDto $categoryDto): Category;

    public function delete(int $id): void;

    public function slugify(string $name): string;

    public function allCategoriesAndChildrenIds(array $categoryIds): array;

    public function sort(CategorySortDto $dto): void;

}
