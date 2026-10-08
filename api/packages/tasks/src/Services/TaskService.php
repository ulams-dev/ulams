<?php

namespace EscolaLms\Tasks\Services;

use EscolaLms\Tasks\Events\TaskIncompleteEvent;
use Illuminate\Support\Carbon;
use EscolaLms\Tasks\Dtos\CreateTaskDto;
use EscolaLms\Tasks\Dtos\PageDto;
use EscolaLms\Tasks\Dtos\CriteriaDto;
use EscolaLms\Tasks\Dtos\OrderDto;
use EscolaLms\Tasks\Dtos\UpdateTaskDto;
use EscolaLms\Tasks\Events\TaskAssignedEvent;
use EscolaLms\Tasks\Events\TaskCompleteRequestEvent;
use EscolaLms\Tasks\Events\TaskCompleteUserConfirmationEvent;
use EscolaLms\Tasks\Events\TaskDeletedEvent;
use EscolaLms\Tasks\Events\TaskUpdatedEvent;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Repositories\Contracts\TaskRepositoryContract;
use EscolaLms\Tasks\Services\Contracts\TaskServiceContract;
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
