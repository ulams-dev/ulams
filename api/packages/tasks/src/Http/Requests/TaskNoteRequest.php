<?php

namespace EscolaLms\Tasks\Http\Requests;

use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Models\TaskNote;
use Illuminate\Foundation\Http\FormRequest;

abstract class TaskNoteRequest extends TaskRequest
{
    public function getTaskNote(?int $taskNoteId = null): TaskNote
    {
        $id = $taskNoteId ?? $this->input('task_id');

        return TaskNote::findOrFail($id);
    }

    public function isTaskNoteOwner(?TaskNote $taskNote = null): bool
    {
        $taskNote = $taskNote ?? $this->getTaskNote();

        if (auth()->id() && $taskNote->user_id === auth()->id()) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [];
    }
}
