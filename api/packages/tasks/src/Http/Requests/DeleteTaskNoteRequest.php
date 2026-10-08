<?php

namespace Ulams\Tasks\Http\Requests;

use Ulams\Tasks\Dtos\CreateTaskNoteDto;
use Ulams\Tasks\Models\TaskNote;
use Illuminate\Support\Facades\Gate;

class DeleteTaskNoteRequest extends TaskNoteRequest
{
    public function authorize(): bool
    {
        $taskNote = $this->getTaskNote($this->route('id'));

        return Gate::allows('deleteOwn', $taskNote) && $this->isTaskNoteOwner($taskNote);
    }

    public function getId(): ?int
    {
        return $this->route('id');
    }
}
