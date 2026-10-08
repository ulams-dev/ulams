<?php

namespace Ulams\Tasks\Services\Contracts;

use Ulams\Tasks\Dtos\CreateTaskNoteDto;
use Ulams\Tasks\Dtos\UpdateTaskNoteDto;
use Ulams\Tasks\Models\TaskNote;

interface TaskNoteServiceContract
{
    public function create(CreateTaskNoteDto $dto): TaskNote;

    public function update(UpdateTaskNoteDto $dto): TaskNote;

    public function delete(int $id);
}
