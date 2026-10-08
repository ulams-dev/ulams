<?php

namespace Ulams\Tasks\Services\Contracts;

use Ulams\Tasks\Dtos\CreateTaskDto;
use Ulams\Tasks\Dtos\CriteriaDto;
use Ulams\Tasks\Dtos\OrderDto;
use Ulams\Tasks\Dtos\PageDto;
use Ulams\Tasks\Dtos\UpdateTaskDto;
use Ulams\Tasks\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TaskServiceContract
{
    public function create(CreateTaskDto $dto): Task;

    public function update(UpdateTaskDto $dto): Task;

    public function delete(int $id): void;

    public function completeOwn(int $id): Task;

    public function complete(int $id): Task;

    public function incomplete(int $id): Task;

    public function findAllByUser(PageDto $pageDto, CriteriaDto $criteriaDto, OrderDto $orderDto): LengthAwarePaginator;

    public function findAll(PageDto $pageDto, CriteriaDto $criteriaDto, OrderDto $orderDto): LengthAwarePaginator;

    public function find(int $id): Task;

    public function findAllOverdue(int $periodStart = 0, int $periodEnd = 0): Collection;
}
