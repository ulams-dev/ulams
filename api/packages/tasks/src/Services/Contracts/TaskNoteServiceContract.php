<?php

namespace EscolaLms\Tasks\Services\Contracts;

use EscolaLms\Tasks\Dtos\CreateTaskNoteDto;
use EscolaLms\Tasks\Dtos\UpdateTaskNoteDto;
use EscolaLms\Tasks\Models\TaskNote;

interface TaskNoteServiceContract
{
    public function create(CreateTaskNoteDto $dto): TaskNote;

    public function update(UpdateTaskNoteDto $dto): TaskNote;

    public function delete(int $id);
}
