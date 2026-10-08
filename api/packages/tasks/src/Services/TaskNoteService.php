<?php

namespace EscolaLms\Tasks\Services;

use EscolaLms\Tasks\Dtos\CreateTaskNoteDto;
use EscolaLms\Tasks\Dtos\UpdateTaskNoteDto;
use EscolaLms\Tasks\Events\TaskNoteCreatedEvent;
use EscolaLms\Tasks\Models\TaskNote;
use EscolaLms\Tasks\Repositories\Contracts\TaskNoteRepositoryContract;
use EscolaLms\Tasks\Services\Contracts\TaskNoteServiceContract;

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
