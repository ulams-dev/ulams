<?php

namespace Ulams\Tasks\Services;

use Ulams\Tasks\Dtos\CreateTaskNoteDto;
use Ulams\Tasks\Dtos\UpdateTaskNoteDto;
use Ulams\Tasks\Events\TaskNoteCreatedEvent;
use Ulams\Tasks\Models\TaskNote;
use Ulams\Tasks\Repositories\Contracts\TaskNoteRepositoryContract;
use Ulams\Tasks\Services\Contracts\TaskNoteServiceContract;

class TaskNoteService implements TaskNoteServiceContract
{
    private TaskNoteRepositoryContract $taskNoteRepository;

    public function __construct(TaskNoteRepositoryContract $taskNoteRepository)
    {
        $this->taskNoteRepository = $taskNoteRepository;
    }

    public function create(CreateTaskNoteDto $dto): TaskNote
    {
        /** @var TaskNote $taskNote */
        $taskNote = $this->taskNoteRepository->create($dto->toArray());

        event(new TaskNoteCreatedEvent($taskNote->notifyTo(), $taskNote));

        return $taskNote;
    }

    public function update(UpdateTaskNoteDto $dto): TaskNote
    {
        return $this->taskNoteRepository->update($dto->toArray(), $dto->getId());
    }

    public function delete(int $id)
    {
        $this->taskNoteRepository->delete($id);
    }
}
