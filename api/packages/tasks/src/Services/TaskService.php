<?php

namespace Ulams\Tasks\Services;

use Ulams\Tasks\Events\TaskIncompleteEvent;
use Illuminate\Support\Carbon;
use Ulams\Tasks\Dtos\CreateTaskDto;
use Ulams\Tasks\Dtos\PageDto;
use Ulams\Tasks\Dtos\CriteriaDto;
use Ulams\Tasks\Dtos\OrderDto;
use Ulams\Tasks\Dtos\UpdateTaskDto;
use Ulams\Tasks\Events\TaskAssignedEvent;
use Ulams\Tasks\Events\TaskCompleteRequestEvent;
use Ulams\Tasks\Events\TaskCompleteUserConfirmationEvent;
use Ulams\Tasks\Events\TaskDeletedEvent;
use Ulams\Tasks\Events\TaskUpdatedEvent;
use Ulams\Tasks\Models\Task;
use Ulams\Tasks\Repositories\Contracts\TaskRepositoryContract;
use Ulams\Tasks\Services\Contracts\TaskServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskService implements TaskServiceContract
{
    private TaskRepositoryContract $taskRepository;

    public function __construct(TaskRepositoryContract $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function create(CreateTaskDto $dto): Task
    {
        /** @var Task $task */
        $task = $this->taskRepository->create($dto->toArray());

        if ($task->isAssigned()) {
            event(new TaskAssignedEvent($task->user, $task));
        }

        return $task;
    }

    public function update(UpdateTaskDto $dto): Task
    {
        /** @var Task $task */
        $task = $this->taskRepository->update($dto->toArray(), $dto->getId());

        if ($task->isAssigned()) {
            event(new TaskUpdatedEvent($task->user, $task));
        }

        return $task;
    }

    public function delete(int $id): void
    {
        /** @var Task $task */
        $task = $this->taskRepository->find($id);

        if ($task->isAssigned()) {
            event(new TaskDeletedEvent($task->user, $task));
        }

        $this->taskRepository->delete($id);
    }

    public function completeOwn(int $id): Task
    {
        /** @var Task $task */
        $task = $this->taskRepository->find($id);

        if ($task->isOwner()) {
            $task->completed_at = Carbon::now();
            $task = $this->taskRepository->update($task->toArray(), $task->getKey());
        } else {
            event(new TaskCompleteRequestEvent($task->createdBy, $task));
        }

        return $task;
    }

    public function complete(int $id): Task
    {
        /** @var Task $task */
        $task = $this->taskRepository->find($id);

        $task->completed_at = Carbon::now();
        $this->taskRepository->update($task->toArray(), $task->getKey());

        if (!$task->isOwner()) {
            event(new TaskCompleteUserConfirmationEvent($task->user, $task));
        }

        return $task;
    }

    public function incomplete(int $id): Task
    {
        /** @var Task $task */
        $task = $this->taskRepository->find($id);

        $task->completed_at = null;
        $this->taskRepository->update($task->toArray(), $task->getKey());

        if ($task->isAssigned()) {
            event(new TaskIncompleteEvent($task->user, $task));
        }

        return $task;
    }

    public function findAllByUser(PageDto $pageDto, CriteriaDto $criteriaDto, OrderDto $orderDto): LengthAwarePaginator
    {
        return $this->taskRepository->findAllByUserId(
            auth()->id(),
            $pageDto->getPerPage(),
            $criteriaDto->toArray(),
            $orderDto->getOrderDirection(),
            $orderDto->getOrderBy(),
        );
    }

    public function findAll(PageDto $pageDto, CriteriaDto $criteriaDto, OrderDto $orderDto): LengthAwarePaginator
    {
        return $this->taskRepository->findAll(
            $pageDto->getPerPage(),
            $criteriaDto->toArray(),
            $orderDto->getOrderDirection(),
            $orderDto->getOrderBy(),
        );
    }

    public function findAllOverdue(int $periodStart = 0, int $periodEnd = 0): Collection
    {
        return $this->taskRepository->findAllCompletedByDueDate(
            [Carbon::now()->subDays($periodEnd), Carbon::now()->subDays($periodStart)], false
        );
    }

    public function find(int $id): Task
    {
        return $this->taskRepository->find($id)->load(['taskNotes', 'taskNotes.user']);
    }
}
