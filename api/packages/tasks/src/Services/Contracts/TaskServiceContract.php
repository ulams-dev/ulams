<?php

namespace EscolaLms\Tasks\Services\Contracts;

use EscolaLms\Tasks\Dtos\CreateTaskDto;
use EscolaLms\Tasks\Dtos\CriteriaDto;
use EscolaLms\Tasks\Dtos\OrderDto;
use EscolaLms\Tasks\Dtos\PageDto;
use EscolaLms\Tasks\Dtos\UpdateTaskDto;
use EscolaLms\Tasks\Models\Task;
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
